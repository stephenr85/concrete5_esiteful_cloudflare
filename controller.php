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

        $cloudflareHelper = \Core::make(CloudflareHelper::class);

        if($cloudflareHelper->isEnabled()) {
            $pageEventListener = function($event) use ($cloudflareHelper) {
                if(method_exists($event, 'getPageObject')) {
                    $page = $event->getPageObject();
                } else if(method_exists($event, 'getCollectionVersionObject')) {
                    $page = Page::getByID($event->getCollectionVersionObject()->getCollectionID());
                }
                if(is_object($page)) {
                    $cloudflareHelper->queueCachePurgeURL(\URL::to($page));
                }
            };

            Events::addListener('on_page_update', $pageEventListener);
            Events::addListener('on_page_move', $pageEventListener);
            Events::addListener('on_page_delete', $pageEventListener);
            Events::addListener('on_page_version_approve', $pageEventListener);

            $fileEventListener = function($event) use ($cloudflareHelper) {
                $file = null;
                if(method_exists($event, 'getFileObject')) {
                    $file = $event->getFileObject();
                } else if(method_exists($event, 'getFileVersionObject')) {
                    $file = $event->getFileVersionObject();
                }
                if(is_object($file)) {
                    $cloudflareHelper->queueCachePurgeURL([
                        $file->getDownloadURL(),
                        $file->getURL()
                    ]);
                }
            };

            Events::addListener('on_file_version_add', $fileEventListener);
            Events::addListener('on_file_version_approve', $fileEventListener);
            Events::addListener('on_file_version_deny', $fileEventListener);
            Events::addListener('on_file_version_update_contents', $fileEventListener);
            Events::addListener('on_file_delete', $fileEventListener);

        }
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
