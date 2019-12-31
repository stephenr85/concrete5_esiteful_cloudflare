<?php
namespace Concrete\Package\EsitefulCloudflare\Job;

use Concrete\Core\Job\Job;
use Concrete\Core\Page\Page;
use Package;
use PageTemplate;
use Concrete\Core\Page\Type\Type as PageType;
use PageTheme;
use PageController;
use Concrete\Core\Area\Area;
use Concrete\Package\EsitefulCloudflare\Helpers\CloudflareHelper;

class CloudflareCachePurge extends Job
{

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

    public function run()
    {
        $cloudflareHelper = $this->getCloudflareHelper();
        if(!$cloudflareHelper->isEnabled()) {
            return 'Cloudflare integration is disabled.';
        }
        $q = $cloudflareHelper->getCachePurgeQueue();

        $files = [];
        $tags = [];
        $hosts = [];

        $totalFiles = 0;
        $totalTags = 0;
        $totalHosts = 0;

        $messages = $q->receive(5);
        foreach ($messages as $msg) {
            $msg->body = unserialize($msg->body);

            if(is_array($msg->body['files'])) {
                $files += $msg->body['files'];
            }

            if(is_array($msg->body['tags'])) {
                $tags += $msg->body['tags'];
            }

            if(is_array($msg->body['hosts'])) {
                $hosts += $msg->body['hosts'];
            }

            $q->deleteMessage($msg);
        }

        $to_str_callback = function($thing) {
            return (string)$thing;
        };

        $files = array_unique(array_filter(array_map($to_str_callback, $files)));
        $tags = array_unique(array_filter(array_map($to_str_callback, $tags)));
        $hosts = array_unique(array_filter(array_map($to_str_callback, $hosts)));

        $totalFiles += count($files);
        $totalTags += count($tags);
        $totalHosts += count($hosts);
        $remaining = $q->count();
        //$q->deleteQueue();

        if($totalFiles + $totalTags + $totalHosts > 0) {
            $cloudflareHelper->getApiEndpoint('zones')->cachePurge($cloudflareHelper->getCurrentZoneID(), empty($files) ? null : $files, empty($tags) ? null : $tags, empty($hosts) ? null : $hosts);
        }

        return t("Purged %s URLs, %s tags, %s hosts. Queue remaining: %s", $totalFiles, $totalTags, $totalHosts, $remaining);
    }

}
