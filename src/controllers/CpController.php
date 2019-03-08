<?php

namespace dgrigg\migrationmanagerpro\controllers;

use Craft;
use craft\web\Controller;
use dgrigg\migrationmanagerpro\MigrationManagerPro;

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
        $outstanding = MigrationManagerPro::getInstance()->getBadgeCount();
        if ($outstanding){
            Craft::$app->getSession()->setError(Craft::t('migrationmanagerpro','There are pending migrations to run'));
        }
        return $this->renderTemplate('migrationmanagerpro/index');
    }

    /**
     * Shows migrations
     */
    public function actionMigrations()
    {
        $migrator = Craft::$app->getContentMigrator();
        $pending = $migrator->getNewMigrations();
        $applied = $migrator->getMigrationHistory();
        return $this->renderTemplate('migrationmanagerpro/migrations', array('pending' => $pending, 'applied' => $applied));
    }

}
