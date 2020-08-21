<?php

namespace dgrigg\migrationassistant\services;

use Craft;
use craft\elements\GlobalSet;
use dgrigg\migrationassistant\events\ExportEvent;

class Globals extends BaseMigration
{
    /**
     * @var string
     */
    protected $source = 'global';

    /**
     * @var string
     */
    protected $destination = 'globals';

    /**
     * {@inheritdoc}
     */
    public function exportItem($id, $fullExport = false)
    {
        $set = Craft::$app->globals->getSetById($id);

        if (!$set) {
            return false;
        }

        $newSet = [
            'name' => $set->name,
            'handle' => $set->handle,
            'fieldLayout' => array(),
            'requiredFields' => array(),
        ];

        $this->addManifest($set->handle);
        $this->getFieldLayout($set->getFieldLayout(), $newSet);

        if ($fullExport) {
            $newSet = $this->onBeforeExport($set, $newSet);
        }

        return $newSet;
    }

    /**
     * {@inheritdoc}
     */
    public function importItem(array $data)
    {
        $set = $this->createModel($data);

        $event = $this->onBeforeImport($set, $data);
        if ($event->isValid) {
            $result = Craft::$app->globals->saveSet($event->element);

            if ($result) {
                $this->onAfterImport($event->element, $data);
            } else {
                $this->addError('error', 'Could not save the ' . $data['handle'] . ' global.');
            }
        } else {
            $this->addError('error', 'Error importing ' . $data['handle'] . ' global.');
            $this->addError('error', $event->error);
            return false;
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function createModel(array $data)
    {
        $globalSet = Craft::$app->globals->getSetByHandle($data['handle']);
        if (!$globalSet instanceof GlobalSet) {
            $globalSet = new GlobalSet();
        }

        $globalSet->name = $data['name'];
        $globalSet->handle = $data['handle'];

        $fieldLayout = $this->createFieldLayout($data);
        if ($fieldLayout) {
          $fieldLayout->type = GlobalSet::class;
          $globalSet->setFieldLayout($fieldLayout);
        }

        return $globalSet;
    }
}
