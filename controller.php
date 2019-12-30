<?php

namespace Concrete\Package\EsitefulCloudflare;

use \Loader;
use Route;
use \Events;

use Package;
use Concrete\Core\Page\Page;
use Concrete\Package\EsitefulCloudflare\Helpers\CloudflareHelper;
use Concrete\Core\Job\Job;

/**
 * This is the main controller for the package which controls the functionality like Install/Uninstall etc.
 *
 * @author Stephen Rushing, eSiteful
 */
class Controller extends Package {

	/**
	* Protected data members for controlling the instance of the package
	*/
	protected $pkgHandle = 'esiteful_cloudflare';
	protected $appVersionRequired = '8.0.1';
	protected $pkgVersion = '0.0.2';

	/**
	 * This function returns the functionality description ofthe package.
	 *
	 * @param void
	 * @return string $description
	 * @author Stephen Rushing, eSiteful
	 */
	public function getPackageDescription()
	{
	    return t("Custom package for CloudFlare integration.");
	}

	/**
	 * This function returns the name of the package.
	 *
	 * @param void
	 * @return string $name
	 * @author Stephen Rushing, eSiteful
	 */
	public function getPackageName()
	{
	    return t("eSiteful Cloudflare");
	}


	public function on_start(){

		$this->setupAutoloader();

        //$this->getConfig()->save('api.email', 'les.lee@esiteful.com');
        //$this->getConfig()->save('api.key', '6b434a3f27f8b620427e164b615d636fc0160');


        Events::addListener('on_page_update', function($event) {
            $page = $event->getPageObject();
            if(is_object($page)) {

                $cloudflareHelper = \Core::make(CloudflareHelper::class);
                $cloudflareHelper->addUrlsToCachePurgeQueue([\URL::to($page)]);

            }
        });
	}

	/**
     * Configure the autoloader
     */
    private function setupAutoloader()
    {
        if (file_exists($this->getPackagePath() . '/vendor')) {
            require_once $this->getPackagePath() . '/vendor/autoload.php';
        }
    }

	/**
	 * This function is executed during initial installation of the package.
	 *
	 * @param void
	 * @return void
	 * @author Stephen Rushing, eSiteful
	 */
	public function install()
	{
		$this->setupAutoloader();

	    $pkg = parent::install();

	    // Install Package Items
	    $this->install_jobs($pkg);
	}

	/**
	 * This function is executed during upgrade of the package.
	 *
	 * @param void
	 * @return void
	 * @author Stephen Rushing, eSiteful
	 */
	public function upgrade()
	{
		parent::upgrade();
		$pkg = Package::getByHandle($this->getPackageHandle());
	    // Install Package Items
	    $this->install_jobs($pkg);
	}

	/**
	 * This function is executed during uninstallation of the package.
	 *
	 * @param void
	 * @return void
	 * @author Stephen Rushing, eSiteful
	 */
	public function uninstall()
	{
	    $pkg = parent::uninstall();
	}


	/**
	 * This function is used to install jobs.
	 *
	 * @param type $pkg
	 * @return void
	 * @author Stephen Rushing, eSiteful
	 */
	function install_jobs($pkg){

        $job = Job::getByHandle('cloudflare_cache_purge');
        if(!is_object($job)) {
            $job = Job::installByPackage('cloudflare_cache_purge', $pkg);
        }

	}


}
