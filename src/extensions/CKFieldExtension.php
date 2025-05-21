<?php

namespace dgrigg\migrationassistant\extensions;

use Craft;
use yii\base\Event;
use dgrigg\migrationassistant\events\ExportEvent;
use dgrigg\migrationassistant\events\ImportEvent;
use dgrigg\migrationassistant\services\BaseMigration;
use dgrigg\migrationassistant\services\BaseContentMigration;
use dgrigg\migrationassistant\helpers\ElementHelper;
use craft\base\Element;
use craft\elements\Entry;
use craft\elements\Category;
use craft\elements\Asset;
use Exception;

class CKFieldExtension
{

    function __construct(){

        Event::on(BaseMigration::class, BaseMigration::EVENT_BEFORE_EXPORT_FIELD_VALUE, function (ExportEvent $event) {
        
            $element = $event->element;
            if ($element->className() == 'craft\ckeditor\Field') {
                $value = $event->value;
                $service = $event->service;
                $values = [];
                if ($value !== null){
                    foreach($value as $key => $chunk){
                        switch ($chunk->getType()){
                            case 'entry':
                                $chunkContent = [];
                                $service->getContent($chunkContent, $chunk->getEntry());
                                $handle = ElementHelper::getElementHandle($chunk->getEntry());
                                $handle['content'] = $chunkContent;
                                $handle['entryId'] = $chunk->getEntry()->id;
                                $values[] = $handle;
                                break;
                            case 'markup';
                                //replace the linked elements with handles
                                $pattern = "/{(entry|asset|category):(\d+)\@(\d):(.*?)}/";
                                $html = preg_replace_callback($pattern, function($matches){
                                    switch ($matches[1]) {
                                        case 'entry':
                                            $query = Entry::find();
                                            $query->elementType = Entry::class;
                                            $query->with(['section']);
                                            break;
                                        case 'category':
                                            $query = Category::find();
                                            $query->elementType = Category::class;
                                            break;
                                        case 'asset':
                                            $query = Asset::find();
                                            $query->elementType = Asset::class;
                                            break;
                                    };

                                    $query->id = $matches[2];
                                    $query->siteId = $matches[3];
                                    $element = $query->one();

                                    if ($element){
                                        $handle = ElementHelper::getSourceHandle($element);
                                        if ($handle){
                                            $handle = json_encode($handle);
                                            return "{element:{$handle}}";
                                        } else {
                                            return "{element:not-found-1}";
                                        }
                                    } else {
                                        return "{element:not-found-2}";
                                    }
                                }, $chunk->rawHtml);
                                $chunk->rawHtml = $html;
                                $values[] = $chunk;
                                break;
                        }
                    }
                } else {
                    $value = [];
                }
                $event->value = $values;
            }
        });

        Event::on(BaseContentMigration::class, BaseMigration::EVENT_BEFORE_IMPORT_FIELD_VALUE, function (ImportEvent $event) {
            $element = $event->element;
            
            if ($element->className() == 'craft\ckeditor\Field') {
                $value = $event->value;
                $ownerId = $event->ownerId;
                $service = $event->service;
                $content = []; 
                foreach ($value as $key => &$chunk) {
                    if (isset($chunk['type'])) {
                        $entry = ElementHelper::getChildEntryByHandle($chunk, $ownerId );
                        if ($entry == false){                               
                            $entry = Craft::createObject(Entry::class);
                        }

                        $entry->fieldId = $element->id;
                        $entry->title = $chunk['title'];
                        $entry->slug = $chunk['slug'];
                        $entry->ownerId = $ownerId;

                        $site = Craft::$app->sites->getSiteByHandle($chunk['site']);
                        if ($site){
                            $entry->siteId = $site->id;
                        }

                        $entryType = Craft::$app->entries->getEntryTypeByHandle($chunk['type']);
                        if ($entryType) {
                            $entry->typeId = $entryType->id;
                        }

                        $fields = $chunk['content']['fields'];
                        $service->validateImportValues($fields, 0);
                        $entry->setFieldValues($fields);
                        $value['fields'] = $fields;
                        
                        $entry->setScenario(Element::SCENARIO_ESSENTIALS);

                        if (Craft::$app->elements->saveElement($entry)){
                            $content[] = "<craft-entry data-entry-id=\"{$entry->id}\">&nbsp;</craft-entry>";
                        } else {
                            Craft::error('could not save the CK field child element', __METHOD__);
                        }
                        
                    } else {

                        //replace the linked elements with handles                          
                        $pattern = "/{(element):({.+?})}/";
                        $html = preg_replace_callback($pattern, function($matches){
                            $handle = json_decode($matches[2], true);   
                            $element = ElementHelper::getElementByHandle($handle);
                            //convert link format to {entry:{entryId}@{siteId}:url||{url}}
                            if ($element){
                                $value = "{{$element::lowerDisplayName()}:{$element->id}@{$element->site->id}:url||{$element->url}}";
                            } else {
                                $value = "";
                            }
                            return $value;
                        }, $chunk['rawHtml']);
                        $content[] = $html; 
                    }
                }
                $value = implode("", $content);
                $event->value = $value;           
            }
        });
    }
    
}