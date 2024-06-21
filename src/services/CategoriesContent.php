<?php

namespace dgrigg\migrationassistant\services;

use Craft;
use craft\elements\Category;

class CategoriesContent extends BaseContentMigration
{
    protected $source = 'category';
    protected $destination = 'categories';

    /**
     * @param int $id
     * @param bool $fullExport
     * @return array
     */
    public function exportItem($id, $fullExport = false)
    {
        $primaryCategory = Craft::$app->categories->getCategoryById($id);
        $sites = $primaryCategory->getGroup()->getSiteSettings();
        $content = array(
            'slug' => $primaryCategory->slug,
            'category' => $primaryCategory->getGroup()->handle,
            'sites' => array()
        );

        $this->addManifest($content['slug']);

        if ($primaryCategory->getParent())
        {
            $content['parent'] = $this->exportItem($primaryCategory->getParent()->id, true);
        }

        foreach($sites as $siteSetting){
            $site = Craft::$app->sites->getSiteById($siteSetting->siteId);
            
            if ($site){
                $category = Craft::$app->categories->getCategoryById($id, $site->id);
                $categoryContent = array(
                    'slug' => $category->slug,
                    'category' => $category->getGroup()->handle,
                    'enabled' => $category->enabled,
                    'site' => $site->handle,
                    'enabledForSite' => $category->enabledForSite,
                    'title' => $category->title,
                    'uid' => $category->uid
                );

                if ($category->getParent())
                {
                    $categoryContent['parent'] = $category->getParent()->slug;
                }

                $this->getContent($categoryContent, $category);

                $categoryContent = $this->onBeforeExport($category, $categoryContent);
                $content['sites'][$site->handle] = $categoryContent;
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
        $primaryCategory = Category::find()
            ->group($data['category'])
            ->slug($data['slug'])
            ->one();

        if (array_key_exists('parent', $data))
        {
            $this->importItem($data['parent']);
        }

        foreach($data['sites'] as $value) {
            if ($primaryCategory) {
                $value['id'] = $primaryCategory->id;
            }

            $category = $this->createModel($value);
            $this->validateImportValues($value);

            if (key_exists('fields', $value)) {
               $category->setFieldValues($value['fields']);
            }

            $event = $this->onBeforeImport($category, $value);
            if ($event->isValid) {

                // save category
                $result = Craft::$app->getElements()->saveElement($event->element);
                if ($result) {
                    $this->onAfterImport($event->element, $data);
                } else {
                    $this->addError('Could not save the ' . $data['slug'] . ' category.');
                    foreach ($event->element->getErrors() as $error) {
                        $this->addError(join(',', $error));
                    }
                    return false;
                }
            } else {
                $this->addError('Error importing ' . $data['slug'] . ' category.');
                $this->addError($event->error);
                return false;
            }

            if (!$primaryCategory) {
                $primaryCategory = $category;
            }
        }

        return true;
    }

    /**
     * @param array $data
     * @return Category
     */
    public function createModel(Array $data)
    {
        $category = new Category();

        if (array_key_exists('id', $data)){
            $category->id = $data['id'];
        }

        $group = Craft::$app->categories->getGroupByHandle($data['category']);
        $category->groupId = $group->id;
        $category->siteId = Craft::$app->sites->getSiteByHandle($data['site'])->id;
        $category->slug = $data['slug'];
        $category->enabled = $data['enabled'];
        $category->title = $data['title'];
        if (array_key_exists('uid', $data)){
            $category->uid = $data['uid'];
        }

        if (array_key_exists('parent', $data))
        {
            $parent =Category::find()
                ->group($data['category'])
                ->slug($data['parent'])
                ->one();
            if ($parent) {
                $category->parentId = $parent->id;
            }
        }

        //grab the content id for existing category
        if (!is_null($category->id)){
            $contentCategory = Craft::$app->categories->getCategoryById($category->id, $category->siteId);
            if ($contentCategory) {
                $category->contentId = $contentCategory->contentId;
            }
        }

        return $category;
    }
}