<?php

namespace dgrigg\migrationassistant\services;

use Craft;
use craft\models\Section;
use craft\models\Section_SiteSettings;
use craft\models\EntryType;
use craft\models\Entry;
use craft\models\FieldLayout;
use craft\models\FieldLayoutTab;
use craft\services\Fields;
use dgrigg\migrationassistant\events\ExportEvent;
use dgrigg\migrationassistant\helpers\MigrationManagerHelper;

class Sections extends BaseMigration
{
    /**
     * @var string
     */
    protected $source = 'section';

    /**
     * @var string
     */
    protected $destination = 'sections';

    /**
     * {@inheritdoc}
     */
    public function exportItem($id, $fullExport = false)
    {
        $section = Craft::$app->sections->getSectionById($id);

        if (!$section) {
            return false;
        }

        $newSection = [
          'name' => $section->attributes['name'],
          'handle' => $section->attributes['handle'],
          'type' => $section->attributes['type'],
          'enableVersioning' => $section->attributes['enableVersioning']
        ];

        if (array_key_exists('propogateEntries', $section->attributes)) {
          $newSection['propogateEntries'] = $section->attributes['propagateEntries'];
        }

        if (array_key_exists('propagationMethod', $section->attributes)) {
          $newSection['propagationMethod'] = $section->attributes['propagationMethod'];
        }

        if ($section->type == Section::TYPE_STRUCTURE){
            $newSection['maxLevels'] =  $section->attributes['maxLevels'];
        }

        $this->addManifest($section->attributes['handle']);

        $siteSettings = $section->getSiteSettings();

        $newSection['sites'] = array();

        foreach ($siteSettings as $siteSetting) {
            $site = Craft::$app->sites->getSiteById($siteSetting->siteId);
            $newSection['sites'][$site->handle] = [
                'site' => $site->handle,
                'hasUrls' => $siteSetting->hasUrls,
                'uriFormat' => $siteSetting->uriFormat,
                'enabledByDefault' => $siteSetting->enabledByDefault,
                'template' => $siteSetting->template,
            ];
        }

        $newSection['entrytypes'] = array();

        $sectionEntryTypes = $section->getEntryTypes();
        foreach ($sectionEntryTypes as $entryType) {
          $newEntryType = [
              'sectionHandle' => $section->attributes['handle'],
              'hasTitleField' => $entryType->attributes['hasTitleField'],
              'titleFormat' => $entryType->attributes['titleFormat'],
              'name' => $entryType->attributes['name'],
              'handle' => $entryType->attributes['handle'],
              'requiredFields' => array(),
          ];

          if (array_key_exists('titleLabel', $entryType->attributes)) {
            $newEntryType['titleLabel'] = $entryType->attributes['titleLabel'];
          }

          if ($newEntryType['titleFormat'] === null) {
              unset($newEntryType['titleFormat']);
          }

          $this->getFieldLayout($entryType->getFieldLayout(), $newEntryType);

          array_push($newSection['entrytypes'], $newEntryType);
        }

        if ($fullExport) {
            $newSection = $this->onBeforeExport($section, $newSection);
        }

        return $newSection;
    }

    /**
     * {@inheritdoc}
     */
    public function importItem(array $data)
    {
        $result = true;
        $existing = Craft::$app->sections->getSectionByHandle($data['handle']);

        if ($existing) {
            $this->mergeUpdates($data, $existing);
        }

        $section = $this->createModel($data);

        if ($section !== false){
            $event = $this->onBeforeImport($section, $data);
            if ($event->isValid) {
                if (Craft::$app->sections->saveSection($event->element)) {
                    $this->onAfterImport($event->element, $data);

                    if (!$existing) {
                        //wipe out the default entry type to ensure the correct entry type handle from the migration is used
                        $defaultEntryType = Craft::$app->sections->getEntryTypesBySectionId($section->id);
                        if ($defaultEntryType) {
                            Craft::$app->sections->deleteEntryTypeById($defaultEntryType[0]->id);
                        }
                    }

                    //add entry types
                    foreach ($data['entrytypes'] as $key => $newEntryType) {

                        $existingType = $this->getSectionEntryTypeByHandle($newEntryType['handle'], $section->id);
                        if ($existingType) {
                          $this->mergeEntryType($newEntryType, $existingType);
                        }

                        $entryType = $this->createEntryType($newEntryType, $section);

                        if (!Craft::$app->sections->saveEntryType($entryType)) {
                          $result = false;
                        }
                    }
                } else {
                    $this->addError('error', 'Could not save the ' . $data['handle'] . ' section.');
                    $result = false;
                }

            } else {
                $this->addError('error', 'Error importing ' . $data['handle'] . ' section.');
                $this->addError('error', $event->error);
                return false;
            }
        } else {
            return false;
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function createModel(array $data)
    {
        $section = new Section();
        if (array_key_exists('id', $data)) {
            $section->id = $data['id'];
        }

        $section->name = $data['name'];
        $section->handle = $data['handle'];
        $section->type = $data['type'];
        $section->enableVersioning = $data['enableVersioning'];

        if (array_key_exists('propagateEntries', $data)) {
          $section->propagateEntries = $data['propagateEntries'];
        }

        if (array_key_exists('propagationMethod', $data)) {
          $section->propagationMethod = $data['propagationMethod'];
        }

        if ($section->type == Section::TYPE_STRUCTURE){
            $section->maxLevels = $data['maxLevels'];
        }

        $allSiteSettings = [];
        if (array_key_exists('sites', $data)) {

            foreach ($data['sites'] as $key => $siteData) {
                //determine if locale exists
                $site = Craft::$app->getSites()->getSiteByHandle($key);

                if ($site){
                    $siteSettings = new Section_SiteSettings();
                    $siteSettings->siteId = $site->id;
                    $siteSettings->hasUrls = $siteData['hasUrls'];
                    $siteSettings->uriFormat = $siteData['uriFormat'];
                    $siteSettings->template = $siteData['template'];
                    $siteSettings->enabledByDefault = (bool)$siteData['enabledByDefault'];
                    $allSiteSettings[$site->id] = $siteSettings;
                } else {
                    $this->addError('error', 'Error importing ' . $data['handle'] . ' section, site ' . $key . ' is not defined.');
                    return false;
                }
            }
        }

        $section->setSiteSettings($allSiteSettings);

        return $section;
    }

    /**
     * @param array        $data
     * @param SectionModel $section
     *
     * @return EntryTypeModel
     */
    private function createEntryType(&$data, $section)
    {
        $entryType = new EntryType(array(
            'sectionId' => $section->id,
            'name' => $data['name'],
            'handle' => $data['handle'],
            'hasTitleField' => $data['hasTitleField']
        ));

        if (array_key_exists('titleFormat', $data)) {
            $entryType->titleFormat = $data['titleFormat'];
        }

        if (array_key_exists('titleLabel', $data)) {
          $entryType->titleFormat = $data['titleLabel'];
        }

        if (array_key_exists('id', $data)) {
          $entryType->id = $data['id'];
        }

        if (array_key_exists('uid', $data)) {
          $entryType->uid = $data['uid'];
        }

        $fieldLayout = $this->createFieldLayout($data);
        if ($fieldLayout) {
          $fieldLayout->type = Entry::class;
          $fieldLayout->id = $entryType->id;
          $entryType->setFieldLayout($fieldLayout);
        }

        return $entryType;
    }

    /**
     * @param array        $newSection
     * @param SectionModel $section
     */
    private function mergeUpdates(&$newSection, $section)
    {
        $newSection['id'] = $section->id;

    }

    /**
     * @param array          $newEntryType
     * @param EntryTypeModel $entryType
     */
    private function mergeEntryType(&$newEntryType, $entryType)
    {
        $newEntryType['id'] = $entryType->id;

        if (property_exists($entryType, 'uid')){
          $newEntryType['uid'] = $entryType->uid;
        }
    }

    /**
     * @param string $handle
     * @param int    $sectionId
     *
     * @return bool
     */
    private function getSectionEntryTypeByHandle($handle, $sectionId)
    {
        $entryTypes = Craft::$app->sections->getEntryTypesBySectionId($sectionId);
        foreach ($entryTypes as $entryType) {
            if ($entryType->handle == $handle) {
                return $entryType;
            }
        }

        return false;
    }
}
