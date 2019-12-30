<?php namespace Concrete\Package\EsitefulCloudflare\Helpers;

use Concrete\Core\Application\Application;
use Concrete\Core\Package\Package;

abstract class Helper {

    protected $app;

    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    public function getApplication()
    {
        return $this->app;
    }

    public function getConfig()
    {
        return $this->app->config;
    }

    public function getPackage()
    {
        return Package::getByHandle('esiteful_cloudflare');
    }

}
