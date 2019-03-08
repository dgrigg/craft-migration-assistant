<?php

namespace dgrigg\migrationassistant\controllers;

use Craft;
use craft\web\Controller;
use dgrigg\migrationassistant\MigrationAssistant;

/**
 * Class MigrationManagerController
 */
class CpController extends Controller
{

    /**
     * Index
     */

    public function actionIndex()
    {
        $outstanding = MigrationAssistant::getInstance()->getBadgeCount();
        if ($outstanding){
            Craft::$app->getSession()->setError(Craft::t('migrationassistant','There are pending migrations to run'));
        }
        return $this->renderTemplate('migrationassistant/index');
    }

    /**
     * Shows migrations
     */
    public function actionMigrations()
    {
        $migrator = Craft::$app->getContentMigrator();
        $pending = $migrator->getNewMigrations();
        $applied = $migrator->getMigrationHistory();
        return $this->renderTemplate('migrationassistant/migrations', array('pending' => $pending, 'applied' => $applied));
    }

}
