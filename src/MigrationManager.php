<?php

namespace dgrigg\migrationmanager;

use Craft;
use craft\base\Element;
use craft\base\Plugin;
use craft\elements\Entry;
use craft\elements\Category;
use craft\elements\User;
use craft\events\RegisterUrlRulesEvent;
use craft\events\RegisterElementActionsEvent;
use craft\events\RegisterUserPermissionsEvent;
use craft\services\UserPermissions;
use craft\web\UrlManager;
use craft\web\View;
use craft\web\twig\variables\CraftVariable;
use yii\base\Event;

use dgrigg\migrationmanager\assetbundles\cpsidebar\CpSideBarAssetBundle;
use dgrigg\migrationmanager\assetbundles\cpglobals\CpGlobalsAssetBundle;
use dgrigg\migrationmanager\actions\MigrateCategoryElementAction;
use dgrigg\migrationmanager\actions\MigrateEntryElementAction;
use dgrigg\migrationmanager\actions\MigrateUserElementAction;
use dgrigg\migrationmanager\helpers\MigrationManagerHelper;
use dgrigg\migrationmanager\variables\MigrationManagerVariable;


/**
 * Migration Manager plugin for Craft CMS
 *
 * Create Craft migrations to easily migrate settings and content between website environments.
 *
 * @author    Derrick Grigg
 * @copyright Copyright (c) 2018 Firstborn
 * @link      https://firstborn.com
 * @package   MigrationManager
 * @since     1.0.0
 */



class MigrationManager extends Plugin
{

    // Static Properties
    // =========================================================================

    /**
     * Static property that is an instance of this plugin class so that it can be accessed via
     * Test::$plugin
     *
     * @var Test
     */
    public static $plugin;

    // Public Methods
    // =========================================================================

    /**
     * Set our $plugin static property to this class so that it can be accessed via
     * Test::$plugin
     *
     * Called after the plugin class is instantiated; do any one-time initialization
     * here such as hooks and events.
     *
     * If you have a '/vendor/autoload.php' file, it will be loaded for you automatically;
     * you do not need to load it in your init() method.
     *
     */


    public function init()
    {
        parent::init();
        self::$plugin = $this;

        $this->setComponents([
            'migrations' => \dgrigg\migrationmanager\services\Migrations::class,
            'sites' => \dgrigg\migrationmanager\services\Sites::class,
            'fields' => \dgrigg\migrationmanager\services\Fields::class,
            'sections' => \dgrigg\migrationmanager\services\Sections::class,
            'assetVolumes' => \dgrigg\migrationmanager\services\AssetVolumes::class,
            'assetTransforms' => \dgrigg\migrationmanager\services\AssetTransforms::class,
            'globals' => \dgrigg\migrationmanager\services\Globals::class,
            'tags' => \dgrigg\migrationmanager\services\Tags::class,
            'categories' => \dgrigg\migrationmanager\services\Categories::class,
            'routes' => \dgrigg\migrationmanager\services\Routes::class,
            'userGroups' => \dgrigg\migrationmanager\services\UserGroups::class,
            'systemMessages' => \dgrigg\migrationmanager\services\SystemMessages::class,
            'categoriesContent' => \dgrigg\migrationmanager\services\CategoriesContent::class,
            'entriesContent' => \dgrigg\migrationmanager\services\EntriesContent::class,
            'globalsContent' => \dgrigg\migrationmanager\services\GlobalsContent::class,
            'usersContent' => \dgrigg\migrationmanager\services\UsersContent::class,
        ]);

        // Register our CP routes
        Event::on(
            UrlManager::class,
            UrlManager::EVENT_REGISTER_CP_URL_RULES,
            function (RegisterUrlRulesEvent $event) {
                $event->rules['migrationmanager/migrations'] = 'migrationmanager/cp/migrations';
                $event->rules['migrationmanager/create'] = 'migrationmanager/cp/index';
                $event->rules['migrationmanager'] = 'migrationmanager/cp/index';
            }
        );

        // Register variables
        Event::on(
            CraftVariable::class,
            CraftVariable::EVENT_INIT,
            function (Event $event) {
               /** @var CraftVariable $variable */
               $variable = $event->sender;
               $variable->set('migrationManager', MigrationManagerVariable::class);
            }
         );
   
        // Register actions only if Solo license or user has rights
        if (Craft::$app->getEdition() > Craft::Solo && (Craft::$app->user->checkPermission('createContentMigrations') == true || Craft::$app->getUser()->getIsAdmin())
           || Craft::$app->getEdition() === Craft::Solo) {
           // Register Element Actions
           Event::on(Entry::class, Element::EVENT_REGISTER_ACTIONS,
              function (RegisterElementActionsEvent $event) {
                 $event->actions[] = MigrateEntryElementAction::class;
              }
           );
   
           Event::on(Category::class, Element::EVENT_REGISTER_ACTIONS,
              function (RegisterElementActionsEvent $event) {
                 $event->actions[] = MigrateCategoryElementAction::class;
              }
           );
   
           Event::on(User::class, Element::EVENT_REGISTER_ACTIONS,
              function (RegisterElementActionsEvent $event) {
                 $event->actions[] = MigrateUserElementAction::class;
              }
           );
        }
   
       Event::on(
          UserPermissions::class,
          UserPermissions::EVENT_REGISTER_PERMISSIONS,
          function(RegisterUserPermissionsEvent $event) {
             $event->permissions['Migration Manager'] = [
                'createContentMigrations' => [
                   'label' => 'Create content migrations',
                ],
             ];
          }
       );

        $request = Craft::$app->getRequest();
        if (!$request->getIsConsoleRequest() && $request->getSegment(1) == 'globals'){
            $view = Craft::$app->getView();
            $view->registerAssetBundle(CpGlobalsAssetBundle::class);
            $view->registerJs('new Craft.MigrationManagerGlobalsExport();', View::POS_END);
        }
    }

    public function getCpNavItem()
    {
        $item = parent::getCpNavItem();
        $item['badgeCount'] = $this->getBadgeCount();
        $item['subnav'] = [
            'create' => ['label' => 'Create', 'url' => 'migrationmanager'],
            'migrations' => ['label' => 'Migrations', 'url' => 'migrationmanager/migrations']
        ];
        return $item;
    }

    public function getBadgeCount(){
        $count =  count($this->migrations->getNewMigrations());
        return $count;
    }

}
