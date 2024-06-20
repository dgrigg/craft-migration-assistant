<?php

namespace dgrigg\migrationassistant\services;

use dgrigg\migrationassistant\helpers\MigrationHelper;
use dgrigg\migrationassistant\helpers\ElementHelper;
use dgrigg\migrationassistant\events\ExportEvent;
use dgrigg\migrationassistant\events\ImportEvent;
use Craft;
use craft\fields\BaseOptionsField;
use craft\fields\BaseRelationField;
use craft\base\Element;
use craft\fields\Tags;
use GuzzleHttp\Promise\Is;
use Throwable;

abstract class BaseContentMigration extends BaseMigration
{
    /**
     * @param $content
     * @param $element
     */
    public function getContent(&$content, $element)
    {
        foreach ($element->getFieldLayout()->getCustomFields() as $fieldModel) {
            $this->getFieldContent($content['fields'], $fieldModel, $element);
        }
    }

    /**
     * @param $content
     * @param $fieldModel
     * @param $parent
     */

    public function getFieldContent(&$content, $fieldModel, $parent)
    {
        $field = $fieldModel;
        $value = $parent->getFieldValue($field->handle);

        switch ($field->className()) {
            case 'craft\redactor\Field':
                if ($value) {
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
            default:
                if ($field instanceof BaseRelationField) {
                    ElementHelper::getSourceHandles($value, $this);
                } elseif ($field instanceof BaseOptionsField) {
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
    protected function validateImportValues(&$values, $context = 'global')
    {
        foreach ($values as $key => &$value) {
            $this->validateFieldValue($values, $key, $value, $context);
        }
    }

    /**
     * Set field values for supported custom fields
     *
     * @param $parent - parent element
     * @param $fieldHandle - field handle
     * @param $fieldValue - value in field
     */

    protected function validateFieldValue($parent, $fieldHandle, &$fieldValue, $context)
    {

        $field = Craft::$app->fields->getFieldByHandle($fieldHandle, $context);
        if ($field) {
            if ($field instanceof BaseRelationField) {
                ElementHelper::populateIds($fieldValue);
            } else {
                switch ($field::class) {
                    case 'craft\fields\Matrix':
                        foreach ($fieldValue as $key => &$matrixBlock) {
                            $blockType = MigrationHelper::getMatrixBlockType($matrixBlock['type'], $field->id);
                            if ($blockType) {
                                $blockFields = Craft::$app->fields->getAllFields('matrixBlockType:' . $blockType->id);
                                foreach ($blockFields as &$blockField) {
                                    if ($blockField->className() == 'verbb\supertable\fields\SuperTableField') {
                                        $matrixBlockFieldValue = $matrixBlock['fields'][$blockField->handle];
                                        $this->updateSupertableFieldValue($matrixBlockFieldValue, $blockField);
                                    }
                                }
                                $this->validateImportValues($matrixBlock['fields'], "matrixBlockType:{$blockType->uid}");
                            }
                        }
                        break;

                    case 'benf\neo\Field':
                        foreach ($fieldValue as $key => &$neoBlock) {
                            $blockType = MigrationHelper::getNeoBlockType($neoBlock['type'], $field->id);
                            if ($blockType) {
                                $blockFields = $blockType->getFieldLayout()->getCustomFields();
                                foreach ($blockFields as &$blockTabField) {
                                    $neoBlockField = Craft::$app->fields->getFieldById($blockTabField->fieldId ?? $blockTabField->id);
                                    if ($neoBlockField->className() == 'verbb\supertable\fields\SuperTableField') {
                                        $neoBlockFieldValue = $neoBlock['fields'][$neoBlockField->handle];
                                        $this->updateSupertableFieldValue($neoBlockFieldValue, $neoBlockField);
                                    }
                                }
                                $this->validateImportValues($neoBlock['fields']);
                            }
                        }
                        break;

                    case 'verbb\supertable\fields\SuperTableField':
                        $this->updateSupertableFieldValue($fieldValue, $field);
                        break;
                }
            }
            $value = $this->onBeforeImportFieldValue($field, $fieldValue);
            $fieldValue = $value;
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
                $this->validateImportValues($value['fields'], "superTableBlockType:{$blockType->uid}");
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
            $itemFields = $itemType->getFieldLayout()->getCustomFields();
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
        foreach ($entryTypes as $entryType) {
            if ($entryType->handle == $handle) {
                return $entryType;
            }
        }

        return false;
    }

    /**
     * @param $value
     * @return array
     */
    protected function getSelectedOptions(&$value)
    {
        $options = $value->getOptions();
        $value = [];
        foreach ($options as $option) {
            if ($option->selected) {
                $value[] = $option->value;
            }
        }
        return $value;
    }

}
