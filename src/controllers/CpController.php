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
     * Shows migrations
     */
    public function actionIndex()
    {
        $migrator = Craft::$app->getContentMigrator();
        $pending = $migrator->getNewMigrations();
        $applied = $migrator->getMigrationHistory();
        return $this->renderTemplate('migrationassistant/index', array('pending' => $pending, 'applied' => $applied));
    }

    /**
     * Index
     */

     public function actionCreate()
     {
         
         return $this->renderTemplate('migrationassistant/create');
     }

     /**
     * @throws HttpException
     */
    public function actionStart()
    {
        
        $data = array(
            'data' => array(
                'migrations' =>  '',
                'applied' =>  0,
             ),
            'nextAction' => 'migrationassistant/run/start'
        );

        return $this->renderTemplate('migrationassistant/actions/run', $data);
    }

}
