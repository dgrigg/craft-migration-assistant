<?php

namespace dgrigg\migrationassistant\controllers;

use Craft;
use craft\web\Controller;
use craft\elements\GlobalSet;
use dgrigg\migrationassistant\MigrationAssistant;
use craft\web\assets\updates\UpdatesAsset;


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

        if (MigrationAssistant::getInstance()->migrations->createSettingMigration($post)) {
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
        $this->requireAdmin();

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
    public function actionStart()
    {
        $this->requirePostRequest();
        $request = Craft::$app->getRequest();
        $data = array(
            'data' => array(
                'migrations' =>  $request->getParam('migration', ''),
                'applied' =>  $request->getParam('applied', 0),
             ),
            'nextAction' => 'migrationassistant/run/start'
        );

        return $this->renderTemplate('migrationassistant/actions/run', $data);
    }

    public function actionRerun(){

        $this->requirePostRequest();
        $request = Craft::$app->getRequest();

        if ($request->getParam('migration') == false){
            Craft::$app->getSession()->setError(Craft::t('migrationassistant', 'You must select a migration to re run'));
            return $this->redirectToPostedUrl();
        } else {
            $data = array(
                'data' => array(
                    'migrations' => $request->getParam('migration', ''),
                    'applied' => $request->getParam('applied', 0),
                ),
                'nextAction' => $request->getParam('nextAction', 'migrationassistant/run/start')
            );
            return $this->renderTemplate('migrationassistant/actions/run', $data);
        }
    }
}
