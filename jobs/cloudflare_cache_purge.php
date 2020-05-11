<?php
namespace Concrete\Package\EsitefulCloudflare\Job;

use Core;
use Concrete\Core\Job\QueueableJob;
use Concrete\Core\Page\Page;
use Package;
use PageTemplate;
use Concrete\Core\Page\Type\Type as PageType;
use Concrete\Core\Cache\Page\PageCache;
use PageTheme;
use PageController;
use Concrete\Core\Area\Area;
use Concrete\Package\EsitefulCloudflare\Helpers\CloudflareHelper;
use ZendQueue\Message as ZendQueueMessage;
use ZendQueue\Queue as ZendQueue;
use Log;
use Exception;

class CloudflareCachePurge extends QueueableJob
{
    protected $jQueueBatchSize = 10;

    public function getJobName()
    {
        return t("Cloudflare Cache Purge");
    }

    public function getJobDescription()
    {
        return t("");
    }

    public function getCloudflareHelper()
    {
        return Core::make(CloudflareHelper::class);
    }

    public function getLogger()
    {
        if(!$this->logger)
        {
            $this->logger = Log::withName($this->getLogNamespace());
        }
        return $this->logger;
    }

    public function getLogNamespace()
    {
        $ns = $this->getJobHandle();
        return $ns;
    }

    public function logInfo($message)
    {
        return $this->getLogger()->addInfo($message);
    }

    public function logNotice($message)
    {
        return $this->getLogger()->addNotice($message);
    }

    public function logWarning($message)
    {
        return $this->getLogger()->addwarning($message);
    }

    public function logError($message)
    {
        return $this->getLogger()->addError($message);
    }

    public function log($message)
    {
        if($message instanceof Exception){
            return $this->logError($message);
        } else if(is_object($message) || is_array($message)){
            return $this->logInfo(var_export($message, true));
        } else {
            return $this->logInfo($message);
        }
    }

    public function reset()
    {
        parent::reset();
        // $this->getCloudflareHelper()->getCachePurgeQueue()->deleteQueue();
    }

    public function start(ZendQueue $q)
    {
        $cloudflareHelper = $this->getCloudflareHelper();
        if(!$cloudflareHelper->isEnabled()) {
            $this->markCompleted(0, 'Cloudflare integration is disabled.');
        }

        $purgeQueue = $cloudflareHelper->getCachePurgeQueue();
        $total = $purgeQueue->count();

        if(!$total) {
            $this->markCompleted(0, t('0 items in purge queue.'));
            return;
        }

        for($i=0; $i <= $total / $this->getJobQueueBatchSize(); $i++)
        {
            $q->send(serialize([
                // ...
            ]));
        }

        return t('%s items in purge queue.', $total);
    }

    public function finish(ZendQueue $q)
    {
        $cloudflareHelper = $this->getCloudflareHelper();
        $purgeQueue = $cloudflareHelper->getCachePurgeQueue();
        $total = $purgeQueue->count();

        return t('Job finished. %s items in purge queue.', $total);
    }

    public function processQueueItem(ZendQueueMessage $msg)
    {
        $cloudflareHelper = $this->getCloudflareHelper();
        $purgeQueue = $cloudflareHelper->getCachePurgeQueue();

        $files = [];
        $tags = [];
        $hosts = [];

        $totalFiles = 0;
        $totalTags = 0;
        $totalHosts = 0;

        $messages = $purgeQueue->receive($this->getJobQueueBatchSize());
        foreach ($messages as $msg) {
            $msg->body = unserialize($msg->body);

            if(is_array($msg->body['files'])) {
                $files = array_merge($files, $msg->body['files']);
            }

            if(is_array($msg->body['tags'])) {
                $tags = array_merge($tags, $msg->body['tags']);
            }

            if(is_array($msg->body['hosts'])) {
                $hosts = array_merge($hosts, $msg->body['hosts']);
            }
            $purgeQueue->deleteMessage($msg);
        }

        $to_str_callback = function($thing) {
            $str = (string)$thing;
            return $str;
        };

        $siteUrl = Core::make('url/canonical');

        $to_url_callback = function($thing) use ($siteUrl) {
            $url = (string)$thing;
            if(preg_match('/^\/.*/', $url)) {
                $url = $siteUrl . ltrim($url, '/');
            }
            return $url;
        };

        $files = array_unique(array_filter(array_map($to_url_callback, $files)));
        $tags = array_unique(array_filter(array_map($to_str_callback, $tags)));
        $hosts = array_unique(array_filter(array_map($to_str_callback, $hosts)));

        $totalFiles += count($files);
        $totalTags += count($tags);
        $totalHosts += count($hosts);
        $remaining = $purgeQueue->count();
        //$q->deleteQueue();
        //var_dump($files);die;
        if($totalFiles + $totalTags + $totalHosts > 0) {
            $this->log(['files' => $files, 'tags' => $tags, 'hosts' => $hosts]);
            try {
                $zonesEndpoint = $cloudflareHelper->getApiEndpoint('zones');
                $isSuccess = $zonesEndpoint->cachePurge($cloudflareHelper->getCurrentZoneID(), empty($files) ? null : array_values($files), empty($tags) ? null : array_values($tags), empty($hosts) ? null : array_values($hosts));
            } catch(\GuzzleHttp\Exception\ClientException $ex)
            {
                $this->log($ex);
                $this->log($ex->getRequest());
                $this->log($ex->getRequest()->getBody()->__toString());
                $this->log($ex->getResponse()->getBody()->getContents());
                throw $ex;
            }
            catch(Exception $ex){
               $this->log($ex);
               throw $ex;
            }
        }

        return t("Purged %s URLs, %s tags, %s hosts. Queue remaining: %s", $totalFiles, $totalTags, $totalHosts, $remaining);
    }

}
