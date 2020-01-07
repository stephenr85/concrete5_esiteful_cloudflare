<?php
namespace Concrete\Package\EsitefulCloudflare\MenuItem\ClearCache;

use Concrete\Core\Application\UserInterface\Menu\Item\Controller as MenuItemController;
use Concrete\Core\Page\Page;

class Controller extends MenuItemController
{
    public function displayItem()
    {
        $page = Page::getCurrentPage();
        if(is_object($page) && !$page->isSystemPage()) {
            return true;
        }
    }
}
