<?php namespace Concrete\Package\EsitefulCloudflare\Controller\Tool;

use Concrete\Core\Controller\Controller;
use Concrete\Core\Job\Job;
use Concrete\Package\EsitefulCloudflare\Helpers\CloudflareHelper;
use Concrete\Core\Page\Page;
use URL;
use Symfony\Component\HttpFoundation\JsonResponse;
use Concrete\Core\Cache\Page\PageCache;

class ClearCache extends Controller
{
    public function page()
    {
        $data = [];
        $cloudflareHelper = $this->app->make(CloudflareHelper::class);

        $urls = $this->request->get('urls');
        if(!is_array($urls)) $urls = [];

        if($this->request->get('cID')) {
            $page = Page::getByID($this->request->get('cID'));
            if(is_object($page) && !$page->isError()) {
                $urls[] = URL::to($page);

                $cache = PageCache::getLibrary();
                $cache->purge($page);
                //$page->refreshCache();
            }
        }

        $cloudflareHelper->queueCachePurgeURL($urls);

        $purgeQueue = $cloudflareHelper->getCachePurgeQueue();
        $data['queue_total'] = $purgeQueue->count();

        $job = Job::getByHandle('cloudflare_cache_purge');
        $job->executeJob();
        $data['job_status'] = $job->getJobLastStatusText();

        $data['success'] = 1;

        return new JsonResponse($data);
    }
}
