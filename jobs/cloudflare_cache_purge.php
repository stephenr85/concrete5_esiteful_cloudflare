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
        return \Core::make(CloudflareHelper::class);
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
            //\Log::addInfo(var_export(['files' => $files, 'tags' => $tags, 'hosts' => $hosts], true));
            $cloudflareHelper->getApiEndpoint('zones')->cachePurge($cloudflareHelper->getCurrentZoneID(), empty($files) ? null : $files, empty($tags) ? null : $tags, empty($hosts) ? null : $hosts);
        }

        return t("Purged %s URLs, %s tags, %s hosts. Queue remaining: %s", $totalFiles, $totalTags, $totalHosts, $remaining);
    }

}
