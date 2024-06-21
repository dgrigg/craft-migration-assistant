<?php

namespace dgrigg\migrationassistant\helpers;

use Craft;
use craft\models\FolderCriteria;
use craft\elements\Asset;
use craft\elements\Category;
use craft\elements\Entry;
use craft\elements\Tag;
use craft\elements\User;
use craft\records\GlobalSet;
use craft\records\TagGroup;
use dgrigg\migrationassistant\MigrationAssistant;
use dgrigg\migrationassistant\services\BaseContentMigration;

/**
 * Class ElementHelper
 */
class ElementHelper
{

    /**
     * @param $handle
     * @param $fieldId
     *
     * @return bool|MatrixBlockTypeModel
     */
    public static function getMatrixBlockType($handle, $fieldId)
    {
        $blockTypes = Craft::$app->matrix->getBlockTypesByFieldId($fieldId);
        foreach ($blockTypes as $block) {
            if ($block->handle == $handle) {
                return $block;
            }
        }

        return false;
    }

    /**
     * @param $handle
     * @param $fieldId
     *
     * @return bool|NeoBlockTypeModel
     */
    public static function getNeoBlockType($handle, $fieldId)
    {
        $neo = Craft::$app->plugins->getPlugin('neo');
        $blockTypes = $neo->blockTypes->getByFieldId($fieldId);
        foreach ($blockTypes as $block) {
            if ($block->handle == $handle) {
                return $block;
            }
        }

        return false;
    }

    /**
     * @param array $element
     *
     * @return bool|BaseElementModel|null
     * @throws Exception
     */
    public static function getAssetByHandle($element)
    {

        $volume = Craft::$app->volumes->getVolumeByHandle($element['source']);
        if ($volume) {
            $folderCriteria = new FolderCriteria();

            if (array_key_exists('path', $element) && !empty($element['path'])) {
              $folderCriteria->path = $element['path'];
            } else {
              $folderCriteria->name = $element['folder'];
            }

            $folderCriteria->volumeId = $volume->id;

            $folder = Craft::$app->assets->findFolder($folderCriteria);
            if ($folder) {

                $query = Asset::find();
                $query->volumeId($volume->id);
                $query->folderId($folder->id);
                $query->filename($element['filename']);

                if (array_key_exists('site', $element)){
                    $site = Craft::$app->sites->getSiteByHandle($element['site']);
                    if ($site){
                        $query->siteId($site->id);
                    }
                }

                $asset = $query->one();

                if ($asset) {
                    return $asset;
                }
            }
        }

        return false;
    }



    /**
     * @param array $element
     *
     * @return bool|BaseElementModel|null
     * @throws Exception
     */
    public static function getCategoryByHandle($element)
    {
        $categoryGroup = Craft::$app->categories->getGroupByHandle($element['category']);
        if ($categoryGroup) {

            $query = Category::find();
            $query->groupId($categoryGroup->id);
            $query->slug($element['slug']);

            if (array_key_exists('site', $element)){
                $site = Craft::$app->sites->getSiteByHandle($element['site']);
                if ($site){
                    $query->siteId($site->id);
                }
            }
            
            $category = $query->one();

            if ($category) {
                return $category;
            }
        }

        return false;
    }

    /**
     * @param array $element
     *
     * @return bool|BaseElementModel|null
     * @throws Exception
     */
    public static function getEntryByHandle($element)
    {
        $section = Craft::$app->sections->getSectionByHandle($element['section']);
        if ($section) {
            $query = Entry::find();
            $query->sectionId($section->id);
            $query->anyStatus();
            $query->slug($element['slug']);

            if (array_key_exists('site', $element)){
                $site = Craft::$app->sites->getSiteByHandle($element['site']);
                if ($site){
                    $query->siteId($site->id);
                }
            }

            $entry = $query->one();
            if ($entry) {
                return $entry;
            }
        }

        return false;
    }

    /**
     * @param array $element
     *
     * @return bool|UserModel|null
     */
    public static function getUserByHandle($element)
    {
        $user = Craft::$app->users->getUserByUsernameOrEmail($element['username']);
        if ($user) {
            return $user;
        }

        return false;
    }

    /**
     * @param array $element
     *
     * @return TagModel|null
     * @throws Exception
     */
    public static function getTagByHandle($element)
    {
        $group = Craft::$app->tags->getTagGroupByHandle($element['group']);
        if ($group) {

            if (MigrationAssistant::getInstance()->tagContent->importItem($element)) {
                $query = Tag::find();
                $query->groupId($group->id);
                $query->slug($element['slug']);
                $tag = $query->one();
                
                return $tag;
            } else {
                return false;
            }
        } 
        return false;
    }

    /**
     * @param array $permissions
     *
     * @return array
     */
    public static function getPermissionIds($permissions)
    {
        foreach ($permissions as &$permission) {
            //determine if permission references element, get id if it does
            if (preg_match('/(:)/', $permission)) {
                $permissionParts = explode(":", $permission);
                $element = null;

                if (preg_match('/entries|entrydrafts/', $permissionParts[0])) {
                    $element = Craft::$app->sections->getSectionByHandle($permissionParts[1]);
                } elseif (preg_match('/volume/', $permissionParts[0])) {
                    $element = Craft::$app->volumes->getVolumeByHandle($permissionParts[1]);
                } elseif (preg_match('/categories/', $permissionParts[0])) {
                    $element = Craft::$app->categories->getGroupByHandle($permissionParts[1]);
                } elseif (preg_match('/globalset/', $permissionParts[0])) {
                    $element = Craft::$app->globals->getSetByHandle($permissionParts[1]);
                } elseif (preg_match('/site/', $permissionParts[0])) {
                    $element = Craft::$app->sites->getSiteByHandle($permissionParts[1]);
                }

                if ($element != null) {
                    $permission = $permissionParts[0] . ':' . $element->uid;
                }
            }
        }

        return $permissions;
    }

    /**
     * @param array $permissions
     *
     * @return array
     */
    public static function getPermissionHandles($permissions)
    {
        foreach ($permissions as &$permission) {
            //determine if permission references element, get handle if it does
            if (preg_match('/(:\w)/', $permission)) {
                $permissionParts = explode(":", $permission);
                $element = null;

                if (preg_match('/entries|entrydrafts/', $permissionParts[0])) {
                    $element = Craft::$app->sections->getSectionByUid($permissionParts[1]);
                } elseif (preg_match('/volume/', $permissionParts[0])) {
                    $element = Craft::$app->volumes->getVolumeByUid($permissionParts[1]);
                } elseif (preg_match('/categories/', $permissionParts[0])) {
                    $element = Craft::$app->categories->getGroupByUid($permissionParts[1]);
                } elseif (preg_match('/globalset/', $permissionParts[0])) {
                    $element = ElementHelper::getGlobalSetByUid($permissionParts[1]);
                } elseif (preg_match('/site/', $permissionParts[0])) {
                    $element = Craft::$app->sites->getSiteByUid($permissionParts[1]);
                }

                if ($element != null) {
                    $permission = $permissionParts[0].':'.$element->handle;
                }
            }
        }

        return $permissions;
    }

    /**
     * Get a tag record by uid
     */

    public static function getTagGroupByUid(string $uid): TagGroup
    {
        $query = TagGroup::find();
        $query->andWhere(['uid' => $uid]);
        return $query->one() ?? new TagGroup();
    }

     /**
     * Gets a global set's record by uid.
     *
     * @param string $uid
     * @return GlobalSetRecord
     */
    public static function getGlobalSetByUid(string $uid): GlobalSet
    {
        return GlobalSet::findOne(['uid' => $uid]) ?? new GlobalSet();
    }

    /**
     * @param $value - an array of elements
     * @param $service - migration service calling function
     * @return array - an array of element handle data for linking
     */

    public static function getSourceHandles(&$value, BaseContentMigration $service = null)
    {
        $elements = $value->all();
        $value = [];
        if ($elements) {
            foreach ($elements as $element) {
                $item = ElementHelper::getSourceHandle($element, $service);

                if ($item)
                {
                    $value[] = $item;
                }
            }
        }
 
        return $value;
    }

    /**
     * @param $element - the element to establish source handles for
     * @param $service - migration service calling function
     * @return array - an array of source handle data for linking
     */
    public static function getSourceHandle($element, BaseContentMigration $service = null)
    {
        $item = false;

        switch ($element->className()) {
            case 'craft\elements\Asset':
                $item = [
                    'elementType' => $element->className(),
                    'filename' => $element->filename,
                    'folder' => $element->getFolder()->name,
                    'source' => $element->getVolume()->handle,
                    'path' => $element->getFolder()->path,
                    'site' => $element->getSite()->handle
                ];
                break;
            case 'craft\elements\Category':
                $item = [
                    'elementType' => $element->className(),
                    'slug' => $element->slug,
                    'category' => $element->getGroup()->handle,
                    'site' => $element->getSite()->handle
                ];
                break;
            case 'craft\elements\Entry':
                $item = [
                    'elementType' => $element->className(),
                    'slug' => $element->slug,
                    'section' => $element->getSection()->handle,
                    'site' => $element->getSite()->handle
                ];
                break;
            case 'craft\elements\Tag':
                $tagValue = [];
                $service->getContent($tagValue, $element);
                $item = [
                    'elementType' => $element->className(),
                    'slug' => $element->slug,
                    'title' => $element->title,
                    'group' => $element->getGroup()->handle,
                    'site' => $element->getSite()->handle
                ];

                if (array_key_exists('fields', $tagValue)){
                    $item['fields'] = $tagValue['fields'];
                }

                break;
            case 'craft\elements\User':
                $item = [
                    'elementType' => $element->className(),
                    'username' => $element->username,
                    'site' => $element->getSite()->handle
                ];
                break;
            default:
                $item = null;
        }

        return $item;
    }

    /**
     * @param $value - array of importing typed elements to find database elements for
     * @return array - an array element ids
     */

    public static function populateIds(&$value)
    {
        $ids = [];
        foreach ($value as &$element) {
            if (is_array($element) && key_exists('elementType', $element)) {
                $elementType = str_replace('/', '\\', $element['elementType']);
                $func = null;
                switch ($elementType) {
                    case 'craft\elements\Asset':
                        $func = 'dgrigg\migrationassistant\helpers\ElementHelper::getAssetByHandle';
                        break;
                    case 'craft\elements\Category':
                        $func = 'dgrigg\migrationassistant\helpers\ElementHelper::getCategoryByHandle';
                        break;
                    case 'craft\elements\Entry':                        
                        $func = 'dgrigg\migrationassistant\helpers\ElementHelper::getEntryByHandle';
                        break;
                    case 'craft\elements\Tag':
                        $func = 'dgrigg\migrationassistant\helpers\ElementHelper::getTagByHandle';
                        break;
                    case 'craft\elements\User':
                        $func = 'dgrigg\migrationassistant\helpers\ElementHelper::getUserByHandle';
                        break;
                    default:
                        break;
                }

                if ($func){

                    $item = $func( $element );
                    if ($item)
                    {
                        $ids[] = $item->id;
                        
                    }
                }
            }
        }

        $value = $ids;
        return $ids;
    }

}