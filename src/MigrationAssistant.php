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
use yii\base\Event;

use dgrigg\migrationassistant\assetbundles\cpglobals\CpGlobalsAssetBundle;
use dgrigg\migrationassistant\actions\MigrateCategoryElementAction;
use dgrigg\migrationassistant\actions\MigrateEntryElementAction;
use dgrigg\migrationassistant\actions\MigrateUserElementAction;
use dgrigg\migrationassistant\helpers\FileLog;
use dgrigg\migrationassistant\helpers\LinkFieldHelper;

/**
 * Migration Assistant plugin for Craft CMS
 *
 * Create Craft migrations to easily migrate content between website environments.
 *
 * @author    Derrick Grigg
 * @copyright Copyright (c) 2018 DGrigg Development Inc.
 * @link      https://dgrigg.com
 * @package   MigrationAssistant
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
            $event->rules['migrationassistant/index'] = 'migrationassistant/cp/index';
            $event->rules['migrationassistant/create'] = 'migrationassistant/cp/create';
            $event->rules['migrationassistant'] = 'migrationassistant/cp/index';
         }
      );

      // Register element actions only if Solo license or user has rights
      Event::on(
         Entry::class,
         Element::EVENT_REGISTER_ACTIONS,
         function (RegisterElementActionsEvent $event) {
            if ($this->hasPermissions()) {
               $event->actions[] = MigrateEntryElementAction::class;
            }
         }
      );

      Event::on(
         Category::class,
         Element::EVENT_REGISTER_ACTIONS,
         function (RegisterElementActionsEvent $event) {
            if ($this->hasPermissions()) {
               $event->actions[] = MigrateCategoryElementAction::class;
            }
         }
      );

      Event::on(
         User::class,
         Element::EVENT_REGISTER_ACTIONS,
         function (RegisterElementActionsEvent $event) {
            if ($this->hasPermissions()) {
               $event->actions[] = MigrateUserElementAction::class;
            }
         }
      );

      $this->registerFieldEvents();

      $request = Craft::$app->getRequest();
      if (!$request->getIsConsoleRequest() && $request->getSegment(1) == 'globals') {
         $view = Craft::$app->getView();
         $view->registerAssetBundle(CpGlobalsAssetBundle::class);
         $view->registerJs('new Craft.MigrationManagerGlobalsExport();', View::POS_END);
      }


      Event::on(
         UserPermissions::class,
         UserPermissions::EVENT_REGISTER_PERMISSIONS,
         function (RegisterUserPermissionsEvent $event) {
            $event->permissions[] = [
               'heading' => 'Migration Assistant',
               'permissions' => [
                  'migrationassistant:create' => [
                     'label' => 'Create content migrations',
                  ],
               ]
            ];
         }
      );

      //File Logging
      FileLog::create('migration-assistant-errors', 'dgrigg\migrationassistant\*');
   }

   protected function registerFieldEvents()
   {
      $linkFieldHelper = new LinkFieldHelper();
   }


   private function hasPermissions(): bool
   {
      return Craft::$app->getEdition() > Craft::Solo && (Craft::$app->user->checkPermission('migrationassistant:create') == true || Craft::$app->getUser()->getIsAdmin()) || Craft::$app->getEdition() === Craft::Solo;
   }

   public function getCpNavItem(): ?array
   {
      $item = parent::getCpNavItem();
      $item['badgeCount'] = $this->getBadgeCount();
      $item['subnav'] = [
         'migrations' => ['label' => 'Migrations', 'url' => 'migrationassistant/index'],
         'create' => ['label' => 'Create', 'url' => 'migrationassistant/create'],
      ];
      return $item;
   }

   public function getBadgeCount()
   {
      $count = count($this->migrations->getNewMigrations());
      return $count;
   }
}
