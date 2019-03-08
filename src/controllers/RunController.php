<?php

namespace dgrigg\migrationassistant\controllers;

use Craft;
use craft\web\Controller;
use dgrigg\migrationassistant\MigrationAssistant;

/**
 * Class MigrationManager_RunController
 */
class RunController extends Controller
{
    /**
     * @throws HttpException
     */
    public function actionStart()
    {
        $this->requirePostRequest();
        $request = Craft::$app->getRequest();

        return $this->asJson(array(
                'data' => $request->getParam('data'),
                'alive' => true,
                'nextAction' => 'migrationassistant/run/prepare'
            )
        );
    }

    /**
     * @throws HttpException
     */
    public function actionPrepare()
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        $data = Craft::$app->request->getRequiredParam('data');

        return $this->asJson(array(
            'alive' => true,
            'nextStatus' => Craft::t('app', 'Backing-up database ...'),
            'nextAction' => 'migrationassistant/run/backup',
            'data' => $data,
        ));
    }

    /**
     * @throws HttpException
     */
    public function actionBackup()
    {
        $this->requirePostRequest();

        $data = Craft::$app->request->getRequiredParam('data');
        $backup = Craft::$app->getConfig()->getGeneral()->getBackupOnUpdate();
        $db = Craft::$app->getDb();

        if ($backup) {
            try {
                $db->backup();
                return $this->asJson(array(
                    'alive' => true,
                    'nextStatus' => Craft::t('migrationassistant', 'Running migrations ...'),
                    'nextAction' => 'migrationassistant/run/migrations',
                    'data' => $data,
                ));

            } catch (\Throwable $e) {
                Craft::$app->disableMaintenanceMode();
                

                return $this->asJson(array(
                    'alive' => true,
                    'errorDetails' => $e->getMessage(),
                    'nextStatus' => Craft::t('migrationassistant', 'An error was encountered. Rolling back ...'),
                    'nextAction' => 'migrationassistant/run/rollback',
                    'data' => $data,
                ));
            }
        } else {
            return $this->asJson(array(
                'alive' => true,
                'nextStatus' => Craft::t('migrationassistant', 'Running migrations ...'),
                'nextAction' => 'migrationassistant/run/migrations',
                'data' => $data,
            ));
        }
    }

    /**
     * @throws HttpException
     */
    public function actionMigrations()
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();
        $data = Craft::$app->request->getParam('data');

        $migrations = $data['migrations'];

        if (!is_array($migrations)){
            $migrations = [];
        }

        // give a little on screen pause
        sleep(2);

        $migrationSvc = MigrationAssistant::getInstance()->migrations;

        if ($migrationSvc->runMigrations($migrations)) {
            return $this->asJson(array(
                'alive' => true,
                'finished' => true,
                'returnUrl' => 'migrationassistant/migrations',
            ));
        } else {
            
            return $this->asJson(array(
                'alive' => true,
                'errorDetails' => 'Check the logs for details. ',
                'errors' => $migrationSvc->getErrors('error'),//['error'],
                'nextStatus' => Craft::t('migrationassistant', 'An error was encountered. Rolling back ...'),
                'nextAction' => 'migrationassistant/run/rollback',
                'data' => $data,
            ));
        }
    }

    /**
     * @throws Exception
     * @throws HttpException
     */
    public function actionRollback()
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        // give a little on screen pause
        sleep(2);

        return $this->asJson(array('alive' => true, 'finished' => true, 'rollBack' => true));
    }


}
