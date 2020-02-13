<?php namespace Concrete\Package\EsitefulCloudflare\Helpers;

use Concrete\Core\Application\Application;
use Concrete\Core\Package\Package;
use Concrete\Core\Database\Connection\Connection;

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

    public function getEntityManager()
    {
        return is_object($this->entityManager) ? $this->entityManager : $this->entityManager = $this->getDatabaseConnection()->getEntityManager();
    }

    public function getDatabaseConnection()
    {
        return is_object($this->db) ? $this->db : $this->db = $this->getApplication()->make(Connection::class);
    }

    public function getSite()
    {
        return $this->getApplication()->make('site')->getSite();
    }

    public function getConfig()
    {
        return $this->getApplication()->make('config');
    }

    public function getSiteConfig()
    {
        return $this->getSite()->getConfigRepository();
    }

    public function getPackage()
    {
        return Package::getByHandle('esiteful_cloudflare');
    }

}
