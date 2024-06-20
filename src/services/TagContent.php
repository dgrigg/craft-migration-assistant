<?php

namespace dgrigg\migrationassistant\services;

use Craft;
use craft\elements\Tag;

class TagContent extends BaseContentMigration
{
    protected $source = 'tag';
    protected $destination = 'tags';

    /**
     * @param int $id
     * @param bool $fullExport
     * @return array
     */
    public function exportItem($id, $fullExport = false)
    {
        return false;
    }

    /**
     * @param array $data
     * @return bool
     */
    public function importItem(Array $data)
    {
        $group = Craft::$app->tags->getTagGroupByHandle($data['group']);
        if ($group) {

            $query = Tag::find();
            $query->groupId($group->id);
            $query->slug($data['slug']);
            $tag = $query->one();

            if ($tag) {
                $data['id'] = $tag->id;
            }

            $tag = $this->createModel($data);

            $fields = array_key_exists('fields', $data) ? $data['fields'] : [];
            $this->validateImportValues($fields);
            $tag->setFieldValues($fields);
            $data['fields'] = $fields;
            $event = $this->onBeforeImport($tag, $data);

            if ($event->isValid) {
                $result = Craft::$app->getElements()->saveElement($event->element);
                if ($result) {
                    $this->onAfterImport($event->element, $data);
                } else {
                    $this->addError('Could not save the ' . $data['slug'] . ' tag.');

                    foreach ($event->element->getErrors() as $error) {
                        $this->addError(join(',', $error));
                    }
                    return false;
                }
            } else {
                $this->addError('Error importing ' . $data['slug'] . ' tag.');
                $this->addError($event->error);
                return false;
            }           
        }
        return $tag;
    }

    /**
     * @param array $data
     * @return Tag
     */
    public function createModel(Array $data)
    {
        $tag = new Tag();

        if (array_key_exists('id', $data)){
            $tag->id = $data['id'];
        }

        $tag->slug = $data['slug'];
        $tag->title = $data['title'];

        $site = Craft::$app->sites->getSiteByHandle($data['site']);
        if ($site){
            $tag->siteId = $site->id;
        }

        $group = Craft::$app->tags->getTagGroupByHandle($data['group']);
        if ($group) {
            $tag->groupId = $group->id;
        }

        return $tag;
    }
}