<?php

namespace Concrete\Package\EsitefulCloudflare;

use Route;
use Events;
use Core;
use Package;
use Concrete\Core\Page\Page;
use Concrete\Package\EsitefulCloudflare\Helpers\CloudflareHelper;
use Concrete\Core\Job\Job;
use Concrete\Core\Http\Request;

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
	protected $pkgVersion = '0.0.3';

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


	public function on_start()
    {

		$this->init_autoloader();
        $this->init_event_listeners();
        $this->init_routes();
        $this->init_toolbar_items();

	}

	/**
     * This function initializes the class autolaoder
     *
     * @param void
     * @return void
     * @author Stephen Rushing, eSiteful
     */
    protected function init_autoloader()
    {
        if (file_exists($this->getPackagePath() . '/vendor')) {
            require_once $this->getPackagePath() . '/vendor/autoload.php';
        }
    }

    /**
     * This function initializes the class routes
     *
     * @param void
     * @return void
     * @author Stephen Rushing, eSiteful
     */
    protected function init_routes()
    {
        Route::register('/tools/package/esiteful_cloudflare/clear_cache/page', '\Concrete\Package\EsitefulCloudflare\Controller\Tool\ClearCache::page');
    }

    /**
     * This function initializes the toolbar items
     *
     * @param void
     * @return void
     * @author Stephen Rushing, eSiteful
     */
    protected function init_toolbar_items()
    {
        $request = Request::getInstance();
        /* @var $menuHelper \Concrete\Core\Application\Service\UserInterface\Menu */
        $menuHelper = Core::make('helper/concrete/ui/menu');
        $pkgHandle = $this->pkgHandle;

        if (!$request->isXmlHttpRequest()) {
            Events::addListener('on_before_render', function($event) use ($menuHelper, $pkgHandle) {

                $menuHelper->addPageHeaderMenuItem('clear_cache', $pkgHandle, [
                    'icon' => 'refresh',
                    'label' => t('Clear Cache'),
                    'position' => 'left',
                    'href' => \URL::to('/tools/package/esiteful_cloudflare/clear_cache/page'),
                    'linkAttributes' => [
                        'id' => 'cloudflare-clear-cache-button'
                    ]
                ]);

            });
        }
    }

    /**
     * This function initializes the event listeners
     *
     * @param void
     * @return void
     * @author Stephen Rushing, eSiteful
     */
    protected function init_event_listeners()
    {
        $cloudflareHelper = Core::make(CloudflareHelper::class);
        $config = $cloudflareHelper->getConfig();

        if($cloudflareHelper->isEnabled() && $config->get('esiteful_cloudflare.autopurge.enabled') !== false) {

            // Register page events
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

            foreach($config->get('esiteful_cloudflare.autopurge.page_events') as $eventName) {
                Events::addListener($eventName, $pageEventListener);
            }

            // Register file events
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

            foreach($config->get('esiteful_cloudflare.autopurge.file_events') as $eventName) {
                Events::addListener($eventName, $fileEventListener);
            }

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
		$this->setup_autoloader();

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
