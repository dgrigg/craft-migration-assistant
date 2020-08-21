<?php

namespace dgrigg\migrationassistant\services;

use Craft;
use craft\elements\Tag;
use craft\models\TagGroup;
use dgrigg\migrationassistant\events\ExportEvent;

class Tags extends BaseMigration
{
    /**
     * @var string
     */
    protected $source = 'tag';

    /**
     * @var string
     */
    protected $destination = 'tags';

    /**
     * {@inheritdoc}
     */
    public function exportItem($id, $fullExport = false)
    {
        $tag = Craft::$app->tags->getTagGroupById($id);

        if (!$tag) {
            return false;
        }

        $newTag = [
            'name' => $tag->name,
            'handle' => $tag->handle,
        ];

        $this->addManifest($tag->handle);

        if ($fullExport) {
          $this->getFieldLayout($tag->getFieldLayout(), $newTag);
        }

        if ($fullExport) {
            $newTag = $this->onBeforeExport($tag, $newTag);
        }


        return $newTag;
    }

    /**
     * {@inheritdoc}
     */
    public function importItem(Array $data)
    {
        $existing = Craft::$app->tags->getTagGroupByHandle($data['handle']);

        if ($existing) {
            $this->mergeUpdates($data, $existing);
        }

        $tag = $this->createModel($data);
        $event = $this->onBeforeImport($tag, $data);

        if ($event->isValid) {
            $result = Craft::$app->tags->saveTagGroup($event->element);
            if ($result) {
                $this->onAfterImport($event->element, $data);
            } else {
                $this->addError('error', 'Could not save the ' . $data['handle'] . ' tag.');
            }
        } else {
            $this->addError('error', 'Error importing ' . $data['handle'] . ' tag.');
            $this->addError('error', $event->error);
            return false;
        }

        return $result;
    }

    /**
     * @param array $newSource
     * @param TagGroupModel $source
     */
    private function mergeUpdates(&$newSource, $source)
    {
        $newSource['id'] = $source->id;
    }

    /**
     * @param array $data
     *
     * @return TagGroupModel
     */
    public function createModel(array $data)
    {
        $tag = new TagGroup();
        if (array_key_exists('id', $data)) {
            $tag->id = $data['id'];
        }

        $tag->name = $data['name'];
        $tag->handle = $data['handle'];

        $fieldLayout = $this->createFieldLayout($data);
        if ($fieldLayout) {
          $fieldLayout->type = Tag::class;
          $tag->setFieldLayout($fieldLayout);
        }

        return $tag;
    }
}
