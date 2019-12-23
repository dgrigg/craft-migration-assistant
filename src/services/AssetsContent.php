<?php

namespace dgrigg\migrationassistant\services;

use Craft;
use craft\elements\Asset;
use dgrigg\migrationassistant\helpers\MigrationManagerHelper;

class AssetsContent extends BaseContentMigration
{
    /**
     * @var string
     */
    protected $source = 'asset';

    /**
     * @var string
     */
    protected $destination = 'assets';

    /**
     * {@inheritdoc}
     */
    public function exportItem($id, $fullExport = false)
    {
        $asset = Craft::$app->assets->getAssetById($id);

        $this->addManifest($id);

        if ($asset) {

          $attributes = $this->getSourceHandle($asset, $asset->className());

          Craft::error(\json_encode($asset), 'migrationassistant');

          $content = array();
          $this->getContent($content, $asset);

          $content = array_merge($content, $attributes);

          $content = $this->onBeforeExport($asset, $content);

          Craft::error(\json_encode($content), 'migrationassistant');

          return $content;
        }

        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function importItem(Array $data)
    {
        $asset = MigrationManagerHelper::getAssetByHandle($data);

        Craft::error(json_encode($asset), 'migrationassistant');

        if ($asset) {
            $data['id'] = $asset->id;
            $data['contentId'] = $user->contentId;
        } else {
          Craft::error('asset not found', 'migrationassistant');
          return false;
        }

        $this->validateImportValues($data);

        if (array_key_exists('fields', $data)) {
            $asset->setFieldValues($data['fields']);
        }

        $event = $this->onBeforeImport($asset, $data);
        if ($event->isValid) {

            // save user
            $result = Craft::$app->getElements()->saveElement($event->element);
            if ($result) {
                $this->onAfterImport($event->element, $data);
            } else {
                $this->addError('error', 'Could not save the ' . $data['filename'] . ' asset.');
                $this->addError('error', join(',', $event->element->getErrors()));
                return false;
            }
        } else {
            $this->addError('error', 'Error importing ' . $data['filename']);
            $this->addError('error', $event->error);
            return false;
        }


        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function createModel(array $data)
    {
        $asset = new Asset();

        if (array_key_exists('id', $data)) {
            $user->id = $data['id'];
        }

        //$user->setAttributes($data);
        return $asset;
    }

    /**
     * {@inheritdoc}
     */
    protected function getContent(&$content, $element)
    {
        parent::getContent($content, $element);
    }


}
