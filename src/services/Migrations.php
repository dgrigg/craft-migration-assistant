<?php

namespace dgrigg\migrationassistant\services;

use Craft;
use yii\base\Component;
use craft\helpers\App;
use craft\helpers\FileHelper;
use craft\helpers\StringHelper;
use craft\errors\MigrationException;
use dgrigg\migrationassistant\MigrationAssistant;
use dgrigg\migrationassistant\helpers\MigrationHelper;
use DateTime;

class Migrations extends Component
{
    private $_contentMigrationTypes = array(
        'entry' => 'entriesContent',
        'category' => 'categoriesContent',
        'user' => 'usersContent',
        'global' => 'globalsContent',
    );

    private $errors = [];

    /**
     * create a new migration file based on selected content elements
     *
     * @param $data
     *
     * @return bool
     */
    public function createContentMigration($data)
    {
        $manifest = [];

        $migration = array(
            'content' => array(),
        );
      
        $empty = true;
        $plugin = MigrationAssistant::getInstance();

        foreach ($this->_contentMigrationTypes as $key => $value) {
            $service = $plugin->get($value);

            if (array_key_exists($service->getSource(), $data)) {
                $migration['content'][$service->getDestination()] = $service->export($data[$service->getSource()], true);
                $empty = false;

                if ($service->hasErrors()) {
                    $errors = $service->getErrors();
                    foreach ($errors as $error) {
                        Craft::error($error);
                        $this->addError($error);
                    }

                    return false;
                }
                $manifest = array_merge($manifest, [$key => $service->getManifest()]);
            }
        }

        if ($empty) {
            $migration = null;
        }

        $this->createMigration($migration, $manifest);

        return true;
    }

    /**
     * @param mixed $migration data to write in migration file
     * @param array $manifest
     * @param string $migrationName 
     *
     * @throws Exception
     */
    public function createMigration($migration, $manifest = array(), $migrationName = '')
    {
        $empty = is_null($migration);
        $date = new DateTime();
        $name = 'm%s_migration';
        $description = [];

        if ($migrationName == '') {

            foreach ($manifest as $key => $value) {
                $description[] = $key;
                foreach ($value as $item) {
                    $description[] = $item;
                }
            }
        } else {
            $description[] = $migrationName;
        }

        if (!$empty || count($description)>0) {
            $description = implode('_', $description);
            $name .= '_' . MigrationHelper::slugify($description);
        }

        $filename = sprintf($name, $date->format('ymd_His'));
        $filename = substr($filename, 0, 250);
        $filename = str_replace('-', '_', $filename);

        $migrator = Craft::$app->getContentMigrator();
        $migrationPath = $migrator->migrationPath;

        $path = sprintf($migrationPath . '/%s.php', $filename);

        $pathLen = strlen($path);
        if ($pathLen > 255) {
            $migrationPathLen = strlen($migrationPath);
            $filename = substr($filename, 0, 250 - $migrationPathLen);
            $path = sprintf($migrationPath . '/%s.php', $filename);
        }

        $migration = json_encode($migration, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $content = Craft::$app->view->renderTemplate('migrationassistant/_migration', array('empty' => $empty, 'migration' => $migration, 'className' => $filename, 'manifest' => $manifest, true));

        FileHelper::writeToFile($path, $content);

        // mark the migration as completed if it's not a blank one
        if (!$empty) {
            $migrator->addMigrationHistory($filename);
        }

        return true;
    }

    /**
     * Import data from migration file
     * 
     * @param $daata - json data
     */
    public function import($data)
    {
        $data = iconv('UTF-8', 'UTF-8//IGNORE', StringHelper::convertToUtf8($data));
        $data = json_decode($data, true);
        if (json_last_error() != JSON_ERROR_NONE){
            Craft::error('Migration Assistant JSON error', __METHOD__);
            Craft::error(json_last_error(), __METHOD__);
            Craft::error(json_last_error_msg(), __METHOD__);
        }

        $plugin = MigrationAssistant::getInstance();
             if (array_key_exists('content', $data)) {
            foreach ($this->_contentMigrationTypes as $key => $value) {
                $service = $plugin->get($value);
                if (array_key_exists($service->getDestination(), $data['content'])) {
                    $service->import($data['content'][$service->getDestination()]);
                    if ($service->hasErrors()) {
                        $errors = $service->getErrors();
                        foreach ($errors as $error) {
                            Craft::error($error, __METHOD__);
                            $this->addError($error);
                        }
                        return false;
                    }
                }
            }
        }
        return true;
    }

    /**
     * @param array $migrationsToRun
     *
     * @return bool
     * @throws \CDbException
     */
    public function runMigrations($migrationNames = [])
    {
        // This might take a while
        App::maxPowerCaptain();

        if (empty($migrationNames)) {
            $migrationNames = $this->getNewMigrations();
        }

        $total = count($migrationNames);
        $n = count($migrationNames);

        if ($n === $total) {
            $logMessage = "Total $n new ".($n === 1 ? 'migration' : 'migrations').' to be applied:';
        } else {
            $logMessage = "Total $n out of $total new ".($total === 1 ? 'migration' : 'migrations').' to be applied:';
        }

        foreach ($migrationNames as $migrationName) {
            $logMessage .= "\n\t$migrationName";
        }

        foreach ($migrationNames as $migrationName) {
             try {
                $migrator = Craft::$app->getContentMigrator();
                $migrator->removeMigrationHistory($migrationName);
                $migrator->migrateUp($migrationName);
            } catch (MigrationException $e) {
                Craft::error('Migration failed.', __METHOD__);
                Craft::error($e->getMessage(), __METHOD__);
                Craft::error($e, __METHOD__);
                $this->addError("Migration {$migrationName} failed. The migration process was cancelled. Check the migration logs for details.");

                return false;
            }
        }

        return true;
    }

    /**
     * Gets migrations that have no been applied yet
     *
     * @param BasePlugin $plugin
     *
     * @return array
     * @throws Exception
     */
    public function getNewMigrations()
    {
        $migrator = Craft::$app->getContentMigrator();
        $newMigrations = $migrator->getNewMigrations();
        return $newMigrations;
    }

    public function addError($error){
        $this->errors[] = $error;
    }

    public function getError(){
        if (!empty($this->errors)){
            return $this->errors[0];
        } else {
            false;
        }
    }

    public function getErrors(){
        return $this->errors;
    }


}
