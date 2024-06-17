<?php

namespace dgrigg\migrationassistant\helpers;

use Craft;
use yii\base\Event;
use dgrigg\migrationassistant\events\ExportEvent;
use dgrigg\migrationassistant\events\ImportEvent;
use dgrigg\migrationassistant\services\BaseMigration;
use dgrigg\migrationassistant\services\BaseContentMigration;
use dgrigg\migrationassistant\helpers\ElementHelper;

/**
 * Class LinkFieldHelper
 */
class LinkFieldHelper
{

    function __construct(){

        Event::on(BaseMigration::class, BaseMigration::EVENT_BEFORE_EXPORT_FIELD_VALUE, function (ExportEvent $event) {
        
            $element = $event->element;
            if ($element->className() == 'lenz\linkfield\fields\LinkField') {

                $value = $event->value;
                $linkType = $value->getLinkType();               
                $value = (object)(array) $value;
                $value->type = $linkType->name;
                                        
                if (isset($value->linkedId) && !is_null($value->linkedId)) {
                
                $linkedElement = Craft::$app->elements->getElementById($value->linkedId, $linkType->elementType, $value->linkedSiteId );
                $value->element = [ElementHelper::getSourceHandle($linkedElement)];
                } 

                if (isset($value->linkedSiteId) && !is_null($value->linkedSiteId)) {
                $site = Craft::$app->sites->getSiteById($value->linkedSiteId);
                $value->site = $site->handle;
                }

                unset($value->linkedId);
                unset($value->linkedSiteId);
                $event->value = $value;
            }
        });

        Event::on(BaseContentMigration::class, BaseMigration::EVENT_BEFORE_IMPORT_FIELD_VALUE, function (ImportEvent $event) {
            $element = $event->element;
            $value = $event->value;
            if ($element->className() == 'lenz\linkfield\fields\LinkField') {
            if (key_exists('element', $value)){
                $ids =  ElementHelper::populateIds($value['element']);
                if (count($ids) > 0){
                    $value['linkedId'] = $ids[0];
                    unset($value['element']);
                    
                }
            }

            if (key_exists('site', $value)){
                $site = Craft::$app->sites->getSiteByHandle($value['site']);
                if ($site){
                    $value['linkedSiteId'] = $site->id;
                    unset($value['site']);
                }
            }

            $event->value = $value;           
            }
        });
    }
    
}