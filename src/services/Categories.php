<?php

namespace dgrigg\migrationassistant\services;

use Craft;
use craft\elements\Category;
use craft\models\CategoryGroup;
use craft\models\CategoryGroup_SiteSettings;
use dgrigg\migrationassistant\events\ExportEvent;

class Categories extends BaseMigration
{
    protected $source = 'category';
    protected $destination = 'categories';

    /**
     * @param int $id
     * @param bool $fullExport
     * @return array|bool
     */
    public function exportItem($id, $fullExport = false)
    {
        $category = Craft::$app->categories->getGroupById($id);

        if (!$category) {
            return false;
        }

        $this->addManifest($category->handle);

        $newCategory = [
            'name' => $category->name,
            'handle' => $category->handle,
            'maxLevels' => $category->maxLevels
        ];

        $siteSettings = $category->getSiteSettings();
        $newCategory['sites'] = array();
        foreach ($siteSettings as $siteSetting) {
            $site = Craft::$app->sites->getSiteById($siteSetting->siteId);
            $newCategory['sites'][$site->handle] = [
                'site' => $site->handle,
                'hasUrls' => $siteSetting->hasUrls,
                'uriFormat' => $siteSetting->uriFormat,
                'template' => $siteSetting->template,
            ];
        }

        if ($fullExport) {
          $this->getFieldLayout($category->getFieldLayout(), $newCategory);
        }

        if ($fullExport) {
            $newCategory = $this->onBeforeExport($category, $newCategory);
        }

        return $newCategory;
    }

    /**
     * @param array $data
     * @return bool
     */
    public function importItem(Array $data)
    {
      $existing = Craft::$app->categories->getGroupByHandle($data['handle']);

      if ($existing) {
        $this->mergeUpdates($data, $existing);
      }

      $category = $this->createModel($data);
      $event = $this->onBeforeImport($category, $data);

      if ($event->isValid) {
          $result = Craft::$app->categories->saveGroup($event->element);
          if ($result) {
            $this->onAfterImport($event->element, $data);
          } else {
            $this->addError('error', 'Could not save the ' . $data['handle'] . ' category.');
          }

      } else {
          $this->addError('error', 'Error importing ' . $data['handle'] . ' field.');
          $this->addError('error', $event->error);
          return false;
      }

      return $result;
    }

    /**
     * @param array $data
     * @return CategoryGroup
     */
    public function createModel(Array $data)
    {
        $category = new CategoryGroup();
        if (array_key_exists('id', $data)){
          $category->id = $data['id'];
        }

        if (array_key_exists('uid', $data)) {
          $category->uid = $data['uid'];
        }

        $category->name = $data['name'];
        $category->handle = $data['handle'];
        $category->maxLevels = $data['maxLevels'];

        $allSiteSettings = [];
        if (array_key_exists('sites', $data)) {
            foreach ($data['sites'] as $key => $siteData) {
                //determine if locale exists
                $site = Craft::$app->getSites()->getSiteByHandle($key);
                $siteSettings = new CategoryGroup_SiteSettings();
                $siteSettings->siteId = $site->id;
                $siteSettings->hasUrls = $siteData['hasUrls'];
                $siteSettings->uriFormat = $siteData['uriFormat'];
                $siteSettings->template = $siteData['template'];
                $allSiteSettings[$site->id] = $siteSettings;
            }
            $category->setSiteSettings($allSiteSettings);
        }

        $fieldLayout = $this->createFieldLayout($data);
        if ($fieldLayout) {
          $fieldLayout->type = Category::class;
          $fieldLayout->id = $category->id;
          $category->setFieldLayout($fieldLayout);
        }

        return $category;
    }

    /**
     * @param $newSource
     * @param $source
     */
    private function mergeUpdates(&$newSource, $source)
    {
        $newSource['id'] = $source->id;
        if (property_exists($source, 'uid')){
          $newSource['uid'] = $source->uid;
        }

    }

}