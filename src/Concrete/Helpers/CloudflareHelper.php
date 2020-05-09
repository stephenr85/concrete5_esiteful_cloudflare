<?php namespace Concrete\Package\EsitefulCloudflare\Helpers;

use URL;
use Database;
use Concrete\Core\Foundation\Queue\QueueService;
use Concrete\Core\Page\Page;
use Concrete\Core\Area\Area;
use Concrete\Core\Cache\Page\PageCache;

class CloudflareHelper extends Helper {

    protected $key;
    protected $adapter;

    public function isEnabled($enabled = null)
    {
        if($enabled === true || $enabled === false) {
            $this->getConfig()->set('esiteful_cloudflare.enabled', $enabled);
            return $this;
        }

        return boolval($this->getConfig()->get('esiteful_cloudflare.enabled'));
    }

    public function getApiKey()
    {
        $key = new \Cloudflare\API\Auth\APIKey($this->getConfig()->get('esiteful_cloudflare.api.email'), $this->getConfig()->get('esiteful_cloudflare.api.key'));
        return $key;
    }

    public function getApiAdapter()
    {
        $key = $this->getApiKey();
        $adapter = new \Cloudflare\API\Adapter\Guzzle($key);
        return $adapter;
    }

    public function getApiEndpoint($name)
    {
        $adapter = $this->getApiAdapter();
        $endpointNamespace = '\\Cloudflare\\API\\Endpoints\\';
        $class = $endpointNamespace . camelcase($name);

        if(class_exists($class)) {
            return new $class($adapter);
        } else if(class_exists($name) && in_array(\Cloudflare\API\Endpoints\API::class, class_implements($name))) {
            return new $name($adapter);
        }
    }

    public function getCurrentZoneID()
    {
        $cache = $this->app->make('cache/expensive');
        $cachedZone = $cache->getItem('esiteful_cloudflare/current_zone');
        if($cachedZone->isMiss()) {
            $zone = $this->getApiEndpoint('zones')->getZoneID($this->getConfig()->get('esiteful_cloudflare.api.defaultZone'));
            $cachedZone->set($zone);
            $cache->save($cachedZone);
        } else {
            $zone = $cachedZone->get();
        }
        return $zone;
    }

    public function getCachePurgeQueue()
    {
        $q = $this->app->make(QueueService::class)->get('esiteful_cloudflare_cache_purge');
        return $q;
    }

    public function purgePageCache($cID)
    {
        $page = is_object($cID) ? $cID : Page::getByID($cID);
        $urls = [];
        if(is_object($page) && !$page->isError()) {
            $urls[] = (string)URL::to($page);

            foreach ($page->getPagePaths() as $path) {
                $urls[] = (string)URL::to($path->getPagePath());
            }

            $cache = PageCache::getLibrary();
            $cache->purge($page);
            $page->refreshCache();

            $areas = Area::getListOnPage($page);
            $totalAreas = count($areas);
            $data['areas_cache_cleared'] = $totalAreas;
            foreach($areas as $area) {
                $area->refreshCache($page);
            }

            $blocks = $page->getBlocks();
            $totalBlocks = count($blocks);
            $data['blocks_cache_cleared'] = $totalBlocks;
            foreach($blocks as $block) {
                $block->refreshBlockOutputCache();
                //$block->refreshBlockRecordCache();
            }

            // Get Aliase URLs
            $db = Database::get();
            $aliasPaths = $db->fetchAll('select cPath from PagePaths where cID in (select cID from Pages where cPointerID = ?)', [$page->getCollectionID()]);
            foreach($aliasPaths as $aliasPath) {
                $urls[] = $aliasPath['cPath'];
            }

            // Get additional URLs from attribute
            $otherURLs = $page->getAttribute('cloudflare_cache_purge_urls');
            if($otherURLs) {
                $otherURLs = array_filter(preg_split('/\n/', $otherURLs));
                $urls = array_merge($urls, $otherURLs);
            }

        }

        $this->queueCachePurgeURL($urls);
    }

    public function queueCachePurgeURL($urls)
    {
        if(!is_array($urls)) $urls = [$urls];

        $urls = array_map(function($url){
            return (string)$url;
        }, $urls);

        $this->getCachePurgeQueue()->send(serialize([
            'files' => $urls
        ]));
    }
}
