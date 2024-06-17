<?php

namespace dgrigg\migrationassistant\helpers;

use dgrigg\migrationassistant\services\BaseContentMigration;

/**
 * Class ElementHelper
 */
class ElementHelper
{

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
                    'group' => $element->getGroup()->handle,
                    'value' => $tagValue
                ];
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
                         $func = 'dgrigg\migrationassistant\helpers\MigrationHelper::getAssetByHandle';
                        break;
                    case 'craft\elements\Category':
                        $func = 'dgrigg\migrationassistant\helpers\MigrationHelper::getCategoryByHandle';
                        break;
                    case 'craft\elements\Entry':                        
                        $func = 'dgrigg\migrationassistant\helpers\MigrationHelper::getEntryByHandle';
                        break;
                    case 'craft\elements\Tag':
                        $func = 'dgrigg\migrationassistant\helpers\MigrationHelper::getTagByHandle';
                        break;
                    case 'craft\elements\User':
                        $func = 'dgrigg\migrationassistant\helpers\MigrationHelper::getUserByHandle';
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