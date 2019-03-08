<?php

namespace dgrigg\migrationassistant;

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

use dgrigg\migrationassistant\assetbundles\cpsidebar\CpSideBarAssetBundle;
use dgrigg\migrationassistant\assetbundles\cpglobals\CpGlobalsAssetBundle;
use dgrigg\migrationassistant\actions\MigrateCategoryElementAction;
use dgrigg\migrationassistant\actions\MigrateEntryElementAction;
use dgrigg\migrationassistant\actions\MigrateUserElementAction;
use dgrigg\migrationassistant\helpers\MigrationManagerHelper;
use dgrigg\migrationassistant\variables\MigrationManagerVariable;


/**
 * Migration Assistant plugin for Craft CMS
 *
 * Create Craft migrations to easily migrate settings and content between website environments.
 *
 * @author    Derrick Grigg
 * @copyright Copyright (c) 2018 DGrigg Development Inc.
 * @link      https://firstborn.com
 * @package   MigrationManager
 * @since     1.0.0
 */



class MigrationAssistant extends Plugin
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
            'migrations' => \dgrigg\migrationassistant\services\Migrations::class,
            'sites' => \dgrigg\migrationassistant\services\Sites::class,
            'fields' => \dgrigg\migrationassistant\services\Fields::class,
            'sections' => \dgrigg\migrationassistant\services\Sections::class,
            'assetVolumes' => \dgrigg\migrationassistant\services\AssetVolumes::class,
            'assetTransforms' => \dgrigg\migrationassistant\services\AssetTransforms::class,
            'globals' => \dgrigg\migrationassistant\services\Globals::class,
            'tags' => \dgrigg\migrationassistant\services\Tags::class,
            'categories' => \dgrigg\migrationassistant\services\Categories::class,
            'routes' => \dgrigg\migrationassistant\services\Routes::class,
            'userGroups' => \dgrigg\migrationassistant\services\UserGroups::class,
            'systemMessages' => \dgrigg\migrationassistant\services\SystemMessages::class,
            'categoriesContent' => \dgrigg\migrationassistant\services\CategoriesContent::class,
            'entriesContent' => \dgrigg\migrationassistant\services\EntriesContent::class,
            'globalsContent' => \dgrigg\migrationassistant\services\GlobalsContent::class,
            'usersContent' => \dgrigg\migrationassistant\services\UsersContent::class,
        ]);

        // Register our CP routes
        Event::on(
            UrlManager::class,
            UrlManager::EVENT_REGISTER_CP_URL_RULES,
            function (RegisterUrlRulesEvent $event) {
                $event->rules['migrationassistant/migrations'] = 'migrationassistant/cp/migrations';
                $event->rules['migrationassistant/create'] = 'migrationassistant/cp/index';
                $event->rules['migrationassistant'] = 'migrationassistant/cp/index';
            }
        );

        // Register variables
        Event::on(
            CraftVariable::class,
            CraftVariable::EVENT_INIT,
            function (Event $event) {
               /** @var CraftVariable $variable */
               $variable = $event->sender;
               $variable->set('migrationassistant', MigrationManagerVariable::class);
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
             $event->permissions['Migration Assistant'] = [
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
        //$item['label'] = 'Migrations';
        $item['badgeCount'] = $this->getBadgeCount();
        $item['subnav'] = [
            'create' => ['label' => 'Create', 'url' => 'migrationassistant'],
            'migrations' => ['label' => 'Migrations', 'url' => 'migrationassistant/migrations']
        ];
        return $item;
    }

    public function getBadgeCount(){
        $count =  count($this->migrations->getNewMigrations());
        return $count;
    }

}
