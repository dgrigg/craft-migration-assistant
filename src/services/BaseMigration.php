<?php

namespace dgrigg\migrationassistant\services;

use Craft;
use craft\base\Component;
use craft\base\Element;
use craft\models\FieldLayout;
use craft\models\FieldLayoutTab;
use craft\services\Fields;
use dgrigg\migrationassistant\events\ExportEvent;
use dgrigg\migrationassistant\events\ImportEvent;
use dgrigg\migrationassistant\helpers\MigrationManagerHelper;

/**
 * Class MigrationManager_BaseMigrationService
 */
abstract class BaseMigration extends Component implements IMigrationService
{
    /**
     * @event ExportEvent The event that is triggered before an element is exported
     */

    const EVENT_BEFORE_EXPORT_ELEMENT = 'beforeExport';

   /**
    * @event ExportEvent The event that is triggered before an element is exported
    */

    const EVENT_BEFORE_EXPORT_FIELD_VALUE = 'beforeExportFieldValue';

    /**
     * @event ImportEvent The event that is triggered before an element is imported, can be cancelled
     */
    const EVENT_BEFORE_IMPORT_ELEMENT = 'beforeImport';

   /**
    * @event ImportEvent The event that is triggered before an element is exported
    */

   const EVENT_BEFORE_IMPORT_FIELD_VALUE = 'beforeImportFieldValue';

    /**
     * @event ImportEvent The event that is triggered before an element is imported
     */
    const EVENT_AFTER_IMPORT_ELEMENT = 'afterImport';


    /**
     * @var array
     */
    //protected $errors = array();

    /**
     * @var
     */
    protected $source;

    /**
     * @var
     */
    protected $destination;

    /**
     * @var
     */
    protected $manifest;

    /**
     * @return string
     */
    public function getDestination()
    {
        return $this->destination;
    }

    /**
     * @return string
     */
    public function getSource()
    {
        return $this->source;
    }


    /**
     * @return void
     */
    public function resetManifest()
    {
        $this->manifest = array();
    }

    /**
     * @param mixed $value
     */
    public function addManifest($value)
    {
        $this->manifest[] = $value;
    }

    /**
     * @return array
     */
    public function getManifest()
    {
        return $this->manifest;
    }

    /**
     * @param array $data
     *
     * @return BaseModel|null
     */
    public function createModel(array $data)
    {
        return null;
    }

    /**
     * @param array $ids        array of fields ids to export
     * @param bool  $fullExport flag to export all element data including extending settings and field tabs
     * @return array
     */
    public function export(array $ids, $fullExport = false)
    {
        $this->resetManifest();
        $items = array();

        foreach ($ids as $id) {
            $obj = $this->exportItem($id, $fullExport);
            if ($obj) {
                $items[] = $obj;
            }
        }

        return $items;
    }

    /**
     * @param array $data of data to import
     *
     * @return bool
     */
    public function import(array $data)
    {
        $this->clearErrors();
        $result = true;

        foreach ($data as $section) {
            if ($this->importItem($section) === false) {
                $result = false;
            }
        }

        return $result;
    }

    /**
     * @param int  $id
     * @param bool $fullExport
     *
     * @return mixed
     */
    abstract public function exportItem($id, $fullExport = false);

    /**
     * @param array $data
     *
     * @return mixed
     */
    abstract public function importItem(array $data);

    /**
     * Create field layout array for export
     *
     * @param FieldLayout $fieldLayout Field layout to get info from
     * @param array $newElement array to store field layout for migration
     *
     * @return Boolean
     */
    public function getFieldLayout($fieldLayout, array &$newElement)
    {

      if (MigrationManagerHelper::isVersion('3.5')) {
        $newElement['fieldLayouts'] = array();
        $newElement['fieldLayouts']['tabs'] = array();
        foreach ($fieldLayout->getTabs() as $tab) {
          $tabConfig = $tab->getConfig();
          foreach($tabConfig['elements'] as &$tabElement) {
            if ($tabElement['type'] == 'craft\\fieldlayoutelements\\CustomField') {
              $field = Craft::$app->fields->getFieldByUid($tabElement['fieldUid']);
              $tabElement['fieldHandle'] = $field->handle;
              unset($tabElement['fieldUid']);
            }
          }
          $newElement['fieldLayouts']['tabs'][] = $tabConfig;
        }
      } else {
        $newElement['fieldLayout'] = array();
        foreach ($fieldLayout->getTabs() as $tab) {
          $newElement['fieldLayout'][$tab->name] = array();
          foreach ($tab->getFields() as $tabField) {
            $newElement['fieldLayout'][$tab->name][] = $tabField->handle;
            if ($tabField->required) {
              $newElement['requiredFields'][] = $tabField->handle;
            }
          }
        }
      }

      return true;

    }

    /**
     * Create FieldLayout for import
     * @param array $data data to pull layout info from
     * @return FieldLayout
     */

    public function createFieldLayout($data)
    {
      if (!array_key_exists('fieldLayout', $data) && !array_key_exists('fieldLayouts', $data)){
        return false;
      }

      $requiredFields = array();
      if (array_key_exists('requiredFields', $data)) {
        foreach ($data['requiredFields'] as $handle) {
          $field = Craft::$app->fields->getFieldByHandle($handle);
          if ($field) {
            $requiredFields[] = $field->id;
          }
        }
      }

      if (MigrationManagerHelper::isVersion('3.5')){
        $fieldLayout = new FieldLayout();
        foreach ($data['fieldLayouts']['tabs'] as &$tab) {
          foreach ($tab['elements'] as $key => &$tabElement) {
            if ($tabElement['type'] == 'craft\\fieldlayoutelements\\CustomField') {
              $existingField = Craft::$app->fields->getFieldByHandle($tabElement['fieldHandle']);
              if ($existingField) {
                $tabElement['fieldUid'] = $existingField->uid;
              } else {
                //remove the field from the layout since it doesn't exist yet
                unset($tab['elements'][$key]);
              }
              unset($tabElement['fieldHandle']);

            }
          }
        }
        Craft::error(json_encode($data['fieldLayouts']['tabs']));
        $fieldLayout->setTabs($data['fieldLayouts']['tabs']);
      } else {
        $layout = [];
        foreach ($data['fieldLayout'] as $key => $fields) {
            $fieldIds = array();
            foreach ($fields as $field) {
                $existingField = Craft::$app->fields->getFieldByHandle($field);
                if ($existingField) {
                    $fieldIds[] = $existingField->id;
                }
            }
            $layout[$key] = $fieldIds;
        }

        $fieldLayout = Craft::$app->fields->assembleLayout($layout, $requiredFields);
      }

      return $fieldLayout;
    }

    /**
     * Fires an 'onBeforeExport' event.
     *
     * @param Event $event
     *          $event->params['element'] - element being exported via migration
     *          $event->params['value'] - current element value, change this value in the event handler to migrate a different value
     *
     * @return null
     */
    public function onBeforeExport($element , array $newElement)
    {
        $event = new ExportEvent(array(
            'element' => $element,
            'value' => $newElement
        ));
        $this->trigger($this::EVENT_BEFORE_EXPORT_ELEMENT, $event);
        return $event->value;
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
    public function onBeforeImport($element, array $data)
    {

        $event = new ImportEvent(array(
            'element' => $element,
            'value' => $data
        ));
        $this->trigger($this::EVENT_BEFORE_IMPORT_ELEMENT, $event);
        return $event;
    }

    /**
     * Fires an 'onAfterImport' event.
     *
     * @param Event $event
     *          $event->params['element'] - model that was imported
     *          $event->params['value'] - data used to create the element model
     *
     * @return null
     */
    public function onAfterImport($element, array $data)
    {
        $event = new ImportEvent(array(
            'element' => $element,
            'value' => $data
        ));

        $this->trigger($this::EVENT_AFTER_IMPORT_ELEMENT, $event);
    }


}