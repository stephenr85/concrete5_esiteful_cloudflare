<?php namespace Concrete\Package\EsitefulCloudflare\Helpers;

use Concrete\Core\Foundation\Queue\QueueService;

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
