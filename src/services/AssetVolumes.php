<?php

namespace dgrigg\migrationassistant\services;

use Craft;
use dgrigg\migrationassistant\events\ExportEvent;
use craft\elements\Asset;
use craft\volumes\Local;
use craft\volumes\MissingVolume;

class AssetVolumes extends BaseMigration
{
    /**
     * @var string
     */
    protected $source = 'assetVolume';

    /**
     * @var string
     */
    protected $destination = 'assetVolumes';

    /**
     * @param int  $id
     * @param bool $fullExport
     *
     * @return array|bool
     */
    public function exportItem($id, $fullExport = false)
    {
        $volume = Craft::$app->volumes->getVolumeById($id);
        if (!$volume) {
            return false;
        }

        $this->addManifest($volume->handle);
        $newVolume = [
            'name' => $volume->name,
            'handle' => $volume->handle,
            'type' => $volume->className(),
            'sortOrder' => $volume->sortOrder,
            'typesettings' => $volume->settings,
        ];

        if ($volume->hasUrls){
          $newVolume['hasUrls'] = 1;
          $newVolume['url'] = $volume->url;
        }

        if ($fullExport) {
          $this->getFieldLayout($volume->getFieldLayout(), $newVolume);
        }

        if ($fullExport) {
          $newVolume = $this->onBeforeExport($volume, $newVolume);
        }

        return $newVolume;
    }

    /**
     * @param array $data
     *
     * @return bool
     * @throws \Exception
     */
    public function importItem(Array $data)
    {
        $existing = Craft::$app->volumes->getVolumeByHandle($data['handle']);
        if ($existing) {
            $this->mergeUpdates($data, $existing);
        }

        $volume = $this->createModel($data);
        $event = $this->onBeforeImport($volume, $data);
        if ($event->isValid) {
            $result = Craft::$app->volumes->saveVolume($event->element);
            if ($result){
                $this->onAfterImport($event->element, $data);
            } else {
                $this->addError('error', 'Failed to save asset volume.');
                $errors = $event->element->getErrors();
                foreach($errors as $error) {
                    $this->addError('error', $error);
                }
                return false;
            }
        } else {
            $this->addError('error', 'Error importing ' . $data['handle'] . ' asset volume.');
            $this->addError('error', $event->error);
            return false;
        }

        $result = true;

        return $result;
    }

    /**
     * @param array $data
     *
     * @return VolumeInterface
     */
    public function createModel(Array $data)
    {
        $volumes = Craft::$app->getVolumes();

        $volume = $volumes->createVolume([
            'id' => array_key_exists('id', $data) ? $data['id'] : null,
            'type' => $data['type'],
            'name' => $data['name'],
            'handle' => $data['handle'],
            'hasUrls' => array_key_exists('hasUrls', $data) ? $data['hasUrls'] : false,
            'url' => array_key_exists('hasUrls', $data) ? $data['url'] : '',
            'sortOrder' => $data['sortOrder'],
            'settings' => $data['typesettings']
        ]);

        $fieldLayout = $this->createFieldLayout($data);
        if ($fieldLayout) {
          $fieldLayout->type = Asset::class;
          $volume->setFieldLayout($fieldLayout);
        }

        return $volume;
    }

    /**
     * @param array               $newVolume
     * @param AssetTransformModel $volume
     */
    private function mergeUpdates(&$newVolume, $volume)
    {
        $newVolume['id'] = $volume->id;
    }
}
