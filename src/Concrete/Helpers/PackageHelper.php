<?php
namespace Concrete\Package\EsitefulCloudflare\Helpers;

use Concrete\Core\Application\ApplicationAwareInterface;
use Concrete\Core\Application\ApplicationAwareTrait;
use Concrete\Core\Attribute\SetFactory as AttributeSetFactory;
use Concrete\Core\Attribute\TypeFactory as AttributeTypeFactory;
use Concrete\Core\Attribute\Type as AttributeType;
use Concrete\Core\Attribute\Key\Category  as AttributeKeyCategory;
use Concrete\Core\Attribute\Key\Key as AttributeKey;
use Concrete\Core\Entity\Attribute\Value\Value\SelectValueOption;
use Concrete\Core\Entity\Attribute\Value\Value\SelectValueOptionList;
use Concrete\Core\Page\Type\Type as PageType;
use Concrete\Core\Page\Template as PageTemplate;
use Concrete\Core\Job\Job;
use Concrete\Core\User\Group\Group as UserGroup;
use Concrete\Core\Block\BlockType\BlockType;
use Concrete\Core\Block\BlockType\Set as BlockTypeSet;
use Concrete\Core\Sharing\SocialNetwork\Link as SocialLink;
use Concrete\Core\Sharing\SocialNetwork\Service as SocialService;
use Concrete\Core\Tree\Node\Type\Category as TreeNodeCategory;
use Concrete\Core\Tree\Node\Type\Topic as TreeNodeTopic;
use Concrete\Core\Tree\Type\Topic as TopicTree;
use Concrete\Core\File\Image\Thumbnail\Type\Type as ThumbnailType;
use Concrete\Core\Entity\File\Image\Thumbnail\Type\Type as ThumbnailTypeEntity;

class PackageHelper extends Helper
{
    use ApplicationAwareTrait;

    protected $pkg;

    public function __construct($pkg)
    {
        $this->pkg = $pkg;
    }

    //region General

    public function getPackage()
    {
        return $this->pkg;
    }

    public function setPackage($pkg)
    {
        $this->pkg = $pkg;
    }

    //endregion General

    //region Attribute Keys

    public function getAttributeKeyCategory($akcHandle)
    {
        if(is_string($akcHandle)){
            $service = $this->app->make('Concrete\Core\Attribute\Category\CategoryService');
            $categoryEntity = $service->getByHandle($akcHandle);
            $category = $categoryEntity->getController();
            return $category;
        }else if(is_object($akcHandle)) {
            return $akcHandle;
        }
    }

    public function getAttributeKey($category, $handle)
    {
        $category = $this->getAttributeKeyCategory($category);
        $attrKey = $category->getByHandle($handle);
        return $attrKey;
    }

    public function upsertAttributeKey($category, $attrSet, $keyType, $data)
    {
        $category = $this->getAttributeKeyCategory($category);

        if(is_string($keyType)) {
            $keyTypeHandle = $keyType;

            $keyType = $this->getAttributeType($keyTypeHandle);

            $typeController = $keyType->getController();
            $defaultKeySettings = $typeController->getAttributeKeySettings();

        }

        $attrKeyHandle = $data['akHandle'];
        $attrKey = $category->getByHandle($attrKeyHandle);
        if (!$attrKey || !intval($attrKey->getAttributeKeyID())) {
            $attrKey = $category->add($keyType, $data, $defaultKeySettings, $this->pkg);
        }
        //$attrKey->saveKey($data);
        $attrKeyController = $attrKey->getController();
        foreach($data as $dataKey => $dataValue) {
            if(method_exists($attrKey, $dataKey)){
                $attrKey->$dataKey($dataValue);
            } else if(method_exists($attrKeyController, $dataKey)) {
                $attrKeyController->$dataKey($dataValue);
            }
        }

        if(isset($data['akIsSearchableIndexed'])) {
            $attrKey->setIsAttributeKeyContentIndexed((bool) $data['akIsSearchableIndexed']);
        }
        if(isset($data['akIsSearchable'])) {
            $attrKey->setIsAttributeKeySearchable((bool) $data['akIsSearchable']);
        }

        $this->getEntityManager()->persist($attrKey);

        $attrKeyController->saveKey($data);

        //$this->getEntityManager()->persist($attrKeyController->getAttributeKeySettings());
        //$this->getEntityManager()->flush();

        // Add attribute key to set
        if(is_string($attrSet)){
            $attrSet = $this->upsertAttributeSet($category, $attrSet, $attrSet, $this->pkg);
        }
        if(is_object($attrSet)){
            $attrSetManager = $category->getSetManager();
            $attrSetManager->addKey($attrSet, $attrKey);
        }
        return $attrKey;
    }

    public function upsertSelectAttributeOption($attrKey, $optionValue)
    {
        $option = $attrKey->getController()->getOptionByValue($optionValue, $attrKey);
        if(!is_object($option)){
            $settings = $attrKey->getAttributeKeySettings();
            $optionList = $settings->getOptionList();
            //dd(json_encode($optionList));
            $option = new SelectValueOption();
            $option->setSelectAttributeOptionValue($optionValue);
            $option->setIsEndUserAdded(false);
            $option->setIsOptionDeleted(false);
            $option->setOptionList($optionList);
            $optionList->getOptions()->add($option);
            $settings->setOptionList($optionList);
            $this->getEntityManager()->persist($settings);
            $this->getEntityManager()->flush();
        } elseif($option->isOptionDeleted()) {
            $option->setIsOptionDeleted(false);
            $this->getEntityManager()->persist($option);
            $this->getEntityManager()->flush();
        }
        return $option;
    }

    //endregion Attribute Keys

    //region Attribute Sets

    public function upsertAttributeSet($category, $setHandle, $setName)    {
        $category = $this->getAttributeKeyCategory($category);

        $factory = $this->app->make('Concrete\Core\Attribute\SetFactory');
        $attrSet = $factory->getByHandle($setHandle);
        if(!is_object($attrSet)){
            $attrSetManager = $category->getSetManager();
            $attrSet = $attrSetManager->addSet($setHandle, $setName, $this->pkg);
        }
        return $attrSet;
    }

    //endregion Attribute Sets

    //region Page Templates


    public function upsertPageTemplate($handle, $name, $thumbnail)    {
        if(!$thumbnail) $thumbnail = $handle.'.png';

        $pageTemplate = PageTemplate::getByHandle($handle);
        if(!$pageTemplate) {
            $pageTemplate = PageTemplate::add($handle, $name, $thumbnail, $this->pkg);
        }else{
            //update
            $pageTemplate->update($handle, $name, $thumbnail);
        }
        return $pageTemplate;
    }

    public function ownPageTemplate($pageTemplate)    {
        $db = Loader::db();
        $ptHandle = is_string($pageTemplate) ? $pageTemplate : $pageTemplate->getPageTemplateName();
        $pkgID = is_numeric($pkg) ? $pkg : $pkg->getPackageID();
        $db->Execute('update PageTemplates set pkgID = ? WHERE pTemplateHandle = ?', [$pkgID, $ptHandle]);
    }

    //endregion Page Templates


    //region Page Types

    public function getPageType($handle)
    {
        return PageType::getByHandle($handle);
    }

    public function ownPageType($pageType)    {
        $db = Loader::db();
        $ptHandle = is_string($pageType) ? $pageType : $pageType->getPageTypeName();
        $pkgID = is_numeric($pkg) ? $pkg : $pkg->getPackageID();
        $db->Execute('update PageTypes set pkgID = ? WHERE ptHandle = ?', [$pkgID, $ptHandle]);
    }


    public function upsertPageType($data)    {
        $pageType = $this->getPageType($data['handle']);
        if(!is_object($pageType)){
            if(!$data['allowedTemplates']) $data['allowedTemplates'] = 'A';
            $defaultData = [
                //'handle' => '',
                //'name' => '', //Note: it does not appear you can pass the t() function in the name
                //'defaultTemplate' => $left_side, // optional item, but wise to add
                //'templates' => array($left_side), //So, in this case, with C above, ONLY left sidebar can be used
                'allowedTemplates' => 'A',
                'ptLaunchInComposer' => false,
                'ptIsFrequentlyAdded' => false
            ];
            $pageType = PageType::add(array_merge($defaultData, $data), $this->pkg);
        }else{
            //update
            $pageType->update($data);
        }
        return $pageType;
    }

    //endregion Page Types

    //region Attribute Types

    public function getAttributeType($atHandle)
    {
        if (is_string($atHandle)) {
            $typeFactory = $this->app->make(AttributeTypeFactory::class);
            /* @var TypeFactory $typeFactory */
            $type = $typeFactory->getByHandle($atHandle);
            return $type;
        }
    }

    public function upsertAttributeType($atHandle, $atName, $akcHandle)    {
        $type = AttributeType::getByHandle($atHandle);
        if(!$type) {
            $type = AttributeType::add($atHandle, $atName, $this->pkg);
        }
        // associate this attribute type with all category keys
        if(is_string($akcHandle)) $akcHandle = [$akcHandle];
        if(is_array($akcHandle)) {
            foreach($akcHandle as $akch) {
                $cKey = AttributeKeyCategory::getByHandle($akch);
                $cKey->associateAttributeKeyType($type);
            }
        }
        return $type;

    }

    public function addAttributeKeyToPageType($pageType, $akHandle)
    {
        // throw new Exception('todo');
        // Looks like new C5 just sets value via $pageType->getPageTypePageTemplateDefaultPageObject()->setAttribute();
        // Consider programatically creating Composer form
    }

    public function addAttributeKeysToPageType($pageType, $akHandles)
    {
        foreach($akHandles as $akHandle) {
            $this->addAttributeKeyToPageType($pageType, $akHandle);
        }
    }

    //endregion AttributeTypes


    //region Jobs

    public function upsertJob($jobHandle)
    {
        $job = Job::getByHandle($jobHandle);
        if(!$job){
            $job = Job::installByPackage($jobHandle, $this->pkg);
        }
        return $job;
    }
    //endregion Jobs


    //region Users

    public function upsertUserGroup($groupName, $groupDescription = '', $parentGroup = false)
    {
        $group = UserGroup::getByName($groupName);
        if(!$group){
            $group = UserGroup::add($groupName, $groupDescription, $parentGroup, $this->pkg);
        }
        return $group;
    }
    //endregion Users


    //region Block Types
    public function upsertBlockTypeSet($btsHandle, $btsName)
    {
        $set = BlockTypeSet::getByHandle($btsHandle);
        if(!$set) {
            $set = BlockTypeSet::add($btsHandle, $btsName, $this->pkg);
        }
        return $set;
    }

    public function upsertBlockType($btHandle)
    {
        $bt = BlockType::getByHandle($btHandle);
        if(!$bt) {
            $bt = BlockType::installBlockTypeFromPackage($btHandle, $this->pkg);
        }
        return $bt;
    }
    //endregion Block Types

    //region Social Sharing

    public function upsertSocialLink($serviceHandle, $linkUrl)
    {
        $service = SocialService::getByHandle($serviceHandle);
        if(is_object($service)) {
            $link = SocialLink::getByServiceHandle($serviceHandle);
            if(!is_object($link)) {
                $link = new \Concrete\Core\Entity\Sharing\SocialNetwork\Link();
            }
            $link->setServiceHandle($serviceHandle);
            $link->setSite($this->getSite());
            $link->setURL($linkUrl);
            $link->save();
        }
    }

    //endregion Social Sharing


    //region Topics

    public function getTreeNodeByName($tree, $name, $nodeType = TreeNodeCategory::class)
    {
        $db = $this->getDatabaseConnection();
        $nodeID = $db->fetchColumn('select treeNodeID from TreeNodes where treeID = ? and treeNodeName = ?',[
            is_object($tree) ? $tree->getTreeID() : $tree,
            $name
        ]);
        return $nodeType::getByID($nodeID);
    }

    public function upsertTopicTree($name, $rootCategory = null)
    {
        $tree = TopicTree::getByName($name);
        if(!is_object($tree)) {
            $tree = TopicTree::add($name);
        }
        return $tree;
    }

    public function upsertTopicCategory($parent, $categoryName)
    {
        if($parent instanceof TopicTree) {
            //$parent = TreeNodeCategory::getByID($parent->getRootTreeNodeObject()->getTreeNodeID())
            $parent = $parent->getRootTreeNodeObject();
        }
        $category = $this->getTreeNodeByName($parent->getTreeID(), $categoryName, TreeNodeCategory::class);
        if(!is_object($category)) {
            $category = TreeNodeCategory::add($categoryName, $parent);
        }
        return $category;
    }

    public function upsertTopic($parent, $topicName)
    {
        if($parent instanceof TopicTree) {
            //$parent = TreeNodeCategory::getByID($parent->getRootTreeNodeObject()->getTreeNodeID())
            $parent = $parent->getRootTreeNodeObject();
        }
        $topic = $this->getTreeNodeByName($parent->getTreeID(), $topicName, TreeNodeTopic::class);
        if(!is_object($topic)) {
            $topic = TreeNodeTopic::add($topicName, $parent);
        }
        return $topic;
    }

    public function upsertThumbnailType($handle, $name, $width, $height, $sizingMode = ThumbnailType::RESIZE_PROPORTIONAL)
    {
        $type = ThumbnailType::getByHandle($handle);
        if(!is_object($type)) {
            $type = new ThumbnailTypeEntity();
            $type->setHandle($handle);
        }

        $type->setName($name);
        $type->setWidth($width);
        $type->setHeight($height);
        $type->setSizingMode($sizingMode);
        $type->save();

        return $type;
    }
}
