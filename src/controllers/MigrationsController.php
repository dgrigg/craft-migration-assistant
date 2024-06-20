<?php

namespace dgrigg\migrationassistant\controllers;

use Craft;
use craft\web\Controller;
use craft\elements\GlobalSet;
use dgrigg\migrationassistant\MigrationAssistant;
use Exception;

/**
 * Class MigrationManagerController
 */
class MigrationsController extends Controller
{

    /**
     * @throws HttpException
     */
    public function actionCreateMigration()
    {
        // Prevent GET Requests
        $this->requirePostRequest();
        $request = Craft::$app->getRequest();
        $post = $request->post();

        if (MigrationAssistant::getInstance()->migrations->createMigration(null, [], $post['migrationName'])) {
            Craft::$app->getSession()->setNotice(Craft::t('migrationassistant', 'Migration created.'));

        } else {
            Craft::$app->getSession()->setError(Craft::t('migrationassistant', 'Could not create migration, check log tab for errors.'));
        }

        return $this->redirectToPostedUrl();
    }

    /**
     * @throws HttpException
     */
    public function actionCreateGlobalsContentMigration()
    {
        $this->requirePostRequest();
        $request = Craft::$app->getRequest();
        $post = $request->post();
        $params['global'] = array($post['setId']);

        if (MigrationAssistant::getInstance()->migrations->createContentMigration($params)) {
            Craft::$app->getSession()->setNotice(Craft::t('migrationassistant','Migration created.'));
        } else {
            Craft::$app->getSession()->setError(Craft::t('migrationassistant','Could not create migration, check log tab for errors.'));
        }

        return $this->redirectToPostedUrl();
    }

    /**
     * @throws HttpException
     */
    public function actionRun()
    {
        $this->requirePostRequest();
        $this->requirePermission('utility:migrations');

        try {

            $request = Craft::$app->request;
            $migrations = $request->getParam('migration', []);
            $rerun = $request->getParam('rerun', 0);
            if (!is_array($migrations)){
                $migrations = [];
            }           

            if ($rerun == 1 && empty($migrations)) {
                $this->setFailFlash(Craft::t('app', "You need to select an applied migration to reapply"));
                return $this->redirect('migrationassistant');
            }
 
            $migrationSvc = MigrationAssistant::getInstance()->migrations;

            if ($migrationSvc->runMigrations($migrations)) {
                $count = count($migrations);
                $suffix = $count == 0 || $count > 1 ? 's' : '';              
                
                $this->setSuccessFlash(Craft::t('app', "Applied " . ($count === 0 ? 'all' : $count) . " migration{$suffix} successfully."));
            } else {
                
                $this->setFailFlash(Craft::t('app', $migrationSvc->getError()));
                $errors = $migrationSvc->getError();
                foreach($errors as $error){
                    Craft::error($error, __METHOD__);
                }
            }
        } catch (Exception $error) {
            Craft::error($error, __METHOD__);
            $this->setFailFlash(Craft::t('app', 'An error occurred while applying the migrations.'));
        }

        return $this->redirect('migrationassistant');
    }

    /**
     * @throws HttpException
     */
    public function actionDump()
    {
        
        // Prevent GET Requests
        //$this->requirePostRequest();
        $request = Craft::$app->getRequest();
       
        MigrationAssistant::getInstance()->migrations->createContentMigration(['user' => [757]]);

        die('dumped');

        
    }
}
