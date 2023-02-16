<?php

namespace dgrigg\migrationassistant\services;

use dgrigg\migrationassistant\helpers\MigrationManagerHelper;
use dgrigg\migrationassistant\events\ExportEvent;
use dgrigg\migrationassistant\events\ImportEvent;
use Craft;
use craft\fields\BaseOptionsField;
use craft\fields\BaseRelationField;
use craft\base\Element;

abstract class BaseContentMigration extends BaseMigration
{

    /**
     * @param $content
     * @param $element
     */
    protected function getContent(&$content, $element){
        foreach ($element->getFieldLayout()->getFields() as $fieldModel) {
            $this->getFieldContent($content['fields'], $fieldModel, $element);
        }
    }

    /**
     * @param $content
     * @param $fieldModel
     * @param $parent
     */

    protected function getFieldContent(&$content, $fieldModel, $parent)
    {
        $field = $fieldModel;
        $value = $parent->getFieldValue($field->handle);

        switch ($field->className()) {
             case 'craft\redactor\Field':
                if ($value){
                    $value = $value->getRawContent();
                } else {
                    $value = '';
                }

                break;
            case 'craft\fields\Matrix':
                $model = $parent[$field->handle];
                $model->limit = null;
                $value = $this->getIteratorValues($model, function ($item) {
                    $itemType = $item->getType();
                    $value = [
                        'type' => $itemType->handle,
                        'enabled' => $item->enabled,
                        'sortOrder' => $item->sortOrder,
                        'fields' => []
                    ];

                    return $value;
                });
                break;
            case 'benf\neo\Field':
                $model = $parent[$field->handle];
                $value = $this->getIteratorValues($model, function ($item) {
                    $itemType = $item->getType();
                    $value = [
                        'type' => $itemType->handle,
                        'enabled' => $item->enabled,
                        'modified' => $item->enabled,
                        'collapsed' => $item->collapsed,
                        'level' => $item->level,
                        'fields' => []
                    ];

                    return $value;
                });
                break;
            case 'verbb\supertable\fields\SuperTableField':

                $model = $parent[$field->handle];

                $value = $this->getIteratorValues($model, function ($item) {
                    $value = [
                        'type' => $item->typeId,
                        'fields' => []
                    ];
                    return $value;
                });

                break;
            case 'craft\fields\Dropdown':
                $value = $value->value;
                break;
            case 'craft\fields\Color':
                //need to make sure hex value goes a string
                $value = (string)$value;
                break;
            case 'typedlinkfield\fields\LinkField':
                $elementTypes = ['asset','category','entry','user'];

                if (in_array($value->type, $elementTypes)){
                    $elementType = 'craft\\elements\\' . ucfirst($value->type);
                    if ($value->value != null){
                      $element = Craft::$app->elements->getElementById($value->value, $elementType, ($parent && $parent->siteId ? $parent->siteId : null));
                      $value->value = [$this->getSourceHandle($element, $elementType)];
                    }
                } elseif ($value->type == 'site'){
                    $site = Craft::$app->sites->getSiteById($value->value);
                    $value->value = $site->handle;
                }
                //convert the field object to an array so we can add a property
                $value = json_decode(json_encode($value), true);
                $value['elementType'] = 'typedlinkfield\\fields\\LinkField';
                break;
            default:
                if ($field instanceof BaseRelationField) {
                    $this->getSourceHandles($value);
                } elseif ($field instanceof BaseOptionsField){
                    $this->getSelectedOptions($value);
                }
                break;
        }

        //export the field value
        $value = $this->onBeforeExportFieldValue($field, $value);
        $content[$field->handle] = $value;
    }


    /**
     * Fires an 'onBeforeImport' event.
     *
     * @param Event $event
     *          $event->params['element'] - model to be imported, manipulate this to change the model before it is saved
     *          $event->params['value'] - data used to create the element model
     *
     * @return null
     */
    public function onBeforeExportFieldValue($element, $data)
    {
       $event = new ExportEvent(array(
          'element' => $element,
          'value' => $data
       ));
       $this->trigger($this::EVENT_BEFORE_EXPORT_FIELD_VALUE, $event);
       return $event->value;
    }

    /**
     * @param $values
     */
    protected function validateImportValues(&$values)
    {
        foreach ($values as $key => &$value) {
           $this->validateFieldValue($values, $key, $value);
           $this->onBeforeImportFieldValue(null, $value);
        }
    }

    protected function validateFieldValue($parent, $fieldHandle, &$fieldValue)
    {
        $field = Craft::$app->fields->getFieldByHandle($fieldHandle);
        if ($field) {
           switch ($field->className()) {
                 case 'craft\fields\Matrix':
                    foreach($fieldValue as $key => &$matrixBlock){
                       $blockType = MigrationManagerHelper::getMatrixBlockType($matrixBlock['type'], $field->id);
                       if ($blockType) {
                          $blockFields = Craft::$app->fields->getAllFields('matrixBlockType:' . $blockType->id);
                          foreach($blockFields as &$blockField){
                             if ($blockField->className() == 'verbb\supertable\fields\SuperTableField') {
                                $matrixBlockFieldValue = $matrixBlock['fields'][$blockField->handle];
                                $this->updateSupertableFieldValue($matrixBlockFieldValue, $blockField);
                             }
                          }
                       }
                    }
                    break;
                 case 'benf\neo\Field':
                     foreach($fieldValue as $key => &$neoBlock){
                         $blockType = MigrationManagerHelper::getNeoBlockType($neoBlock['type'], $field->id);
                         if ($blockType) {
                             $blockTabs = $blockType->getFieldLayout()->getTabs();
                             foreach($blockTabs as $blockTab){
                                 $blockFields = $blockTab->getFields();
                                 foreach($blockFields as &$blockTabField){
                                     $neoBlockField = Craft::$app->fields->getFieldById($blockTabField->fieldId ?? $blockTabField->id);
                                     if ($neoBlockField->className() == 'verbb\supertable\fields\SuperTableField') {
                                         $neoBlockFieldValue = $neoBlock['fields'][$neoBlockField->handle];
                                         $this->updateSupertableFieldValue($neoBlockFieldValue, $neoBlockField);
                                     }
                                 }
                             }
                         }
                     }
                     break;
               case 'verbb\supertable\fields\SuperTableField':
                     $this->updateSupertableFieldValue($fieldValue, $field);
                     break;
            }
        }
    }


   /**
    * Fires an 'onBeforeImport' event.
    *
    * @param Event $event
    *          $event->params['element'] - model to be imported, manipulate this to change the model before it is saved
    *          $event->params['value'] - data used to create the element model
    *
    * @return null
    */
   public function onBeforeImportFieldValue($element, $data)
   {
      $event = new ImportEvent(array(
         'element' => $element,
         'value' => $data
      ));
      $this->trigger($this::EVENT_BEFORE_IMPORT_FIELD_VALUE, $event);
      return $event->value;
   }

    /**
     * @param $fieldValue
     * @param $field
     */
    protected function updateSupertableFieldValue(&$fieldValue, $field)
    {
       $plugin = Craft::$app->plugins->getPlugin('super-table');
       $blockTypes = $plugin->service->getBlockTypesByFieldId($field->id);
       if ($blockTypes) {
          $blockType = $blockTypes[0];
          foreach ($fieldValue as $key => &$value) {
             $value['type'] = $blockType->id;
          }
       }
    }

    /**
     * @param $element
     * @param $settingsFunc
     * @return array
     */
    protected function getIteratorValues($element, $settingsFunc)
    {
        //$items = $element->getIterator();
        $items = $element->all();
        $value = [];
        $i = 1;

        foreach ($items as $item) {
            $itemType = $item->getType();
            $itemFields = $itemType->getFieldLayout()->getFields();
            $itemValue = $settingsFunc($item);
            $fields = [];

            foreach ($itemFields as $field) {
                $this->getFieldContent($fields, $field, $item);
            }

            $itemValue['fields'] = $fields;
            $value['new' . $i] = $itemValue;
            $i++;
        }
        return $value;
    }

    /**
     * @param $handle
     * @param $sectionId
     * @return bool
     */
    protected function getEntryType($handle, $sectionId)
    {
        $entryTypes = Craft::$app->sections->getEntryTypesBySectionId($sectionId);
        foreach($entryTypes as $entryType)
        {
            if ($entryType->handle == $handle){
                return $entryType;
            }

        }

        return false;
    }

    /**
     * @param $value
     * @return array
     */
    protected function getSourceHandles(&$value)
    {
        $elements = $value->all();
        $value = [];
        if ($elements) {
            foreach ($elements as $element) {
                $item = $this->getSourceHandle($element, $element->className());

                if ($item)
                {
                    $value[] = $item;
                }
            }
        }

        return $value;
    }

    /**
     * @param $element
     * @param $type
     * @return array
     */
    protected function getSourceHandle($element, $type)
    {
        $item = false;

        switch ($type) {
            case 'craft\elements\Asset':
                $item = [
                    'elementType' => $element->className(),
                    'filename' => $element->filename,
                    'folder' => $element->getFolder()->name,
                    'source' => $element->getVolume()->handle,
                    'path' => $element->getFolder()->path,
                ];
                break;
            case 'craft\elements\Category':
                $item = [
                    'elementType' => $element->className(),
                    'slug' => $element->slug,
                    'category' => $element->getGroup()->handle
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
                $this->getContent($tagValue, $element);
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
                    'username' => $element->username
                ];
                break;
            default:
                $item = null;
        }

        return $item;
    }

    /**
     * @param $value
     */
    protected function getSourceIds(&$value)
    {
        if (is_array($value))
        {
            if (is_array($value)) {
                $this->populateIds($value);
            } else {
                $this->getSourceIds($value);
            }
        }
        return;
    }

    /**
     * @param $value
     * @return array
     */
    protected function getSelectedOptions(&$value){
        $options = $value->getOptions();
        $value = [];
        foreach($options as $option){
            if ($option->selected)
            {
                $value[] = $option->value;
            }
        }
        return $value;

    }

    /**
     * @param $value
     * @return bool
     */
    protected function populateIds(&$value)
    {
        $isElementField = true;
        $ids = [];
        foreach ($value as &$element) {
            if (is_array($element) && key_exists('elementType', $element)) {
                $elementType = str_replace('/', '\\', $element['elementType']);
                $func = null;

                switch ($elementType) {
                    case 'craft\elements\Asset':
                         $func = 'dgrigg\migrationassistant\helpers\MigrationManagerHelper::getAssetByHandle';
                        break;
                    case 'craft\elements\Category':
                        $func = 'dgrigg\migrationassistant\helpers\MigrationManagerHelper::getCategoryByHandle';
                        break;
                    case 'craft\elements\Entry':
                        $func = 'dgrigg\migrationassistant\helpers\MigrationManagerHelper::getEntryByHandle';
                        break;
                    case 'craft\elements\Tag':
                        $func = 'dgrigg\migrationassistant\helpers\MigrationManagerHelper::getTagByHandle';
                        break;
                    case 'craft\elements\User':
                        $func = 'dgrigg\migrationassistant\helpers\MigrationManagerHelper::getUserByHandle';
                        break;
                    case 'typedlinkfield\fields\LinkField':
                        $element = $this->populateLinkField($element);
                        $isElementField = false;
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
            } else {
                $isElementField = false;
                $this->getSourceIds($element);
            }
        }

        if ($isElementField){
            $value = $ids;
        }

        return true;
    }

    /**
     * Get element linked in Link field
     * @param
     * @return
     */
     /**
     * @param $fieldValue
     * @param $field
     */

   public function populateLinkField($element)
   {
       $elementTypes = ['asset','category','entry','user'];
        if (in_array($element['type'], $elementTypes)){
            $value = $element['value'];
            if ($value != null) {
              $this->populateIds($value);
              if (count($value) > 0){
                  $element['value'] = $value[0];
              }
            } else {
              $value = null;
            }
        } elseif ($element['type'] == 'site'){
            $site = Craft::$app->sites->getSiteByHandle($element['value']);
            if ($site){
                $element['value'] = $site->id;
            }
        }
        return $element;
   }

    /**
     * Look for matrix/supertables/neo that are not localized and update the keys to
     * ensure the site/locale values on child elements remain intact
     * @param BaseElementModel $element
     * @param array $data function foo($method)
    **/
    protected function localizeData(Element $element, Array &$data)
    {

      //
      if (MigrationManagerHelper::isVersion('3.2.0')) {
        //localization not needed since 3.2.0
        return;
      }

      $fieldLayout = $element->getFieldLayout();
      foreach ($fieldLayout->getTabs() as $tab) {
          foreach ($tab->getFields() as $tabField) {
              $field = Craft::$app->fields->getFieldById($tabField->id);
              $fieldValue = $element[$field->handle];
              if (in_array ($field->className() , ['craft\fields\Matrix','verbb\supertable\fields\SuperTableField', 'benf\neo\Field']) ) {
                if ($field->localizeBlocks == false) {
                  $items = $fieldValue->getIterator();
                  $i = 1;
                  foreach ($items as $item) {

                    if (key_exists('new'. $i, $data['fields'][$field->handle])) {
                      $data['fields'][$field->handle][$item->id] = $data['fields'][$field->handle]['new' . $i];
                      unset($data['fields'][$field->handle]['new' . $i]);
                    }
                    $i++;
                  }
                }
              }
          }
        }


    }

}