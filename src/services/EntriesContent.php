<?php

namespace dgrigg\migrationassistant\services;
use Craft;
use craft\elements\Entry;
use craft\helpers\DateTimeHelper;
use dgrigg\migrationassistant\helpers\MigrationHelper;
use DateTime;
use DateTimeZone;
use Log;

class EntriesContent extends BaseContentMigration
{
    protected $source = 'entry';
    protected $destination = 'entries';

    /**
     * @param int $element
     * @param bool $fullExport
     * @return array
     */
    public function exportItem($element, $fullExport = false)
    {
       
        $primaryEntry = Craft::$app->entries->getEntryById($element->id, $element->siteId);

        if ($primaryEntry) {
            $sites = $primaryEntry->getSection()->getSiteIds();

            $content = array(
                'slug' => $primaryEntry->slug,
                'section' => $primaryEntry->getSection()->handle,
                'sites' => array()
            );

            $this->addManifest($content['slug']);

            if ($primaryEntry->getParent()) {
                $content['parent'] = $this->exportItem($primaryEntry->getParent(), true);
            }

            foreach ($sites as $siteId) {
                $site = Craft::$app->sites->getSiteById($siteId);
                if ($site){
                    $entry = Craft::$app->entries->getEntryById($element->id, $siteId);
                    if ($entry) {
                        $entryContent = array(
                            'slug' => $entry->slug,
                            'section' => $entry->getSection()->handle,
                            'enabled' => $entry->enabled,
                            'site' => $site->handle,
                            'enabledForSite' => $entry->enabledForSite,
                            'postDate' => $entry->postDate,
                            'expiryDate' => $entry->expiryDate,
                            'title' => $entry->title,
                            'entryType' => $entry->type->handle,
                            'uid' => $entry->uid
                        );

                        if ($entry->author) {
                            $entryContent['author'] = $entry->author->username;
                        }

                        if ($entry->getParent()) {
                            $entryContent['parent'] = $primaryEntry->getParent()->slug;
                        }

                        $this->getContent($entryContent, $entry);
                        $entryContent = $this->onBeforeExport($entry, $entryContent);

                        $content['sites'][$site->handle] = $entryContent;
                    }
                }
            }
        }
        return $content;
    }

    /**
     * @param array $data
     * @return bool
     */
    public function importItem(Array $data)
    {
        $primaryEntry = Entry::find()
          ->section($data['section'])
          ->slug($data['slug'])
          ->site(Craft::$app->sites->getPrimarySite()->handle)
          ->status(null)
          ->one();

        if (array_key_exists('parent', $data))
        {
            $this->importItem($data['parent']);
        }

        foreach($data['sites'] as $key => $value) {
            if ($primaryEntry) {
              $value['id'] = $primaryEntry->id;
            } else {
              $siteEntry = Entry::find()
                ->section($data['section'])
                ->slug($data['slug'])
                ->site($key)
                ->status(null)
                ->one();

              if ($siteEntry){
                $value['id'] = $siteEntry->id;
              }
            }

            $entry = $this->createModel($value);
            $fields = array_key_exists('fields', $value) ? $value['fields'] : [];
            $this->validateImportValues($fields);
            $entry->setFieldValues($fields);
            $value['fields'] = $fields;
            $event = $this->onBeforeImport($entry, $value);

            if ($event->isValid) {
                $result = Craft::$app->getElements()->saveElement($event->element);
                if ($result) {
                    $this->onAfterImport($event->element, $data);
                } else {
                    $this->addError('Could not save the ' . $data['slug'] . ' entry.');

                    foreach ($event->element->getErrors() as $error) {
                        $this->addError(join(',', $error));
                    }
                    return false;
                }
            } else {
                $this->addError('Error importing ' . $data['slug'] . ' field.');
                $this->addError($event->error);
                return false;
            }

            if (!$primaryEntry) {
                $primaryEntry = $entry;
            }
        }
        return true;
    }

    /**
     * @param array $data
     * @return Entry
     */
    public function createModel(Array $data)
    {
        $entry = new Entry();

        if (array_key_exists('id', $data)){
            $entry->id = $data['id'];
        }

        $section = Craft::$app->sections->getSectionByHandle($data['section']);
        $entry->sectionId = $section->id;

        $entryType = $this->getEntryType($data['entryType'], $entry->sectionId);
        if ($entryType) {
            $entry->typeId = $entryType->id;
        }

        $entry->slug = $data['slug'];
        $entry->postDate = is_null($data['postDate']) ? null : new DateTime($data['postDate']['date'], new DateTimeZone($data['postDate']['timezone']));
        $entry->expiryDate = is_null($data['expiryDate']) ? null : new DateTime($data['expiryDate']['date'], new DateTimeZone($data['expiryDate']['timezone']));

        $entry->enabled = $data['enabled'];
        $entry->enabledForSite = $data['enabledForSite'];

        $entry->siteId = Craft::$app->sites->getSiteByHandle($data['site'])->id;
        $entry->uid = $data['uid'];
        

        if (array_key_exists('author', $data)){
            $author = Craft::$app->users->getUserByUsernameOrEmail($data['author']);
            if ($author){
                $entry->authorId = $author->id;
            }
        }

        if (array_key_exists('parent', $data))
        {
            $query = Entry::find();
            $query->sectionId($entry->sectionId);
            $query->siteId($entry->siteId);
            $query->slug($data['parent']);
            $query->status(null);
            $parent = $query->one();
            if ($parent) {
                $entry->newParentId = $parent->id;
            }
        }

        $entry->title = $data['title'];

        //grab the content id for existing entries
        if (!is_null($entry->id)){
            $contentEntry = Craft::$app->entries->getEntryById($entry->id, $entry->siteId);
            if ($contentEntry) {
                $entry->contentId = $contentEntry->contentId;
            }
        }

        return $entry;
    }
}
