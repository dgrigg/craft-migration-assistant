<?php

namespace dgrigg\migrationassistant\services;

use Craft;
use craft\elements\User;
use dgrigg\migrationassistant\helpers\MigrationHelper;

class UsersContent extends BaseContentMigration
{
    /**
     * @var string
     */
    protected $source = 'user';

    /**
     * @var string
     */
    protected $destination = 'users';

    /**
     * {@inheritdoc}
     */
    public function exportItem($id, $fullExport = false)
    {
        $user = Craft::$app->users->getUserById($id);

        $this->addManifest($id);

        if ($user) {
            $attributes = $user->getAttributes();
            unset($attributes['id']);
            unset($attributes['contentId']);
            unset($attributes['uid']);
            unset($attributes['siteId']);

            $content = array();
            $this->getContent($content, $user);
            $content = array_merge($content, $attributes);

            $content = $this->onBeforeExport($user, $content);

            return $content;
        }

        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function importItem(Array $data)
    {
        Craft::error('IMPORT USER', __METHOD__);
        $user = Craft::$app->users->getUserByUsernameOrEmail($data['username']);

        if (!$user){
            $user = Craft::$app->users->getUserByUsernameOrEmail($data['email']);
        }

        $userState = [];

        if ($user) {
            $data['id'] = $user->id;
            $data['contentId'] = $user->contentId;

            $userState['active'] = $user->active;
            $userState['pending'] = $user->pending;
            $userState['locked'] = $user->locked;
            $userState['suspended'] = $user->suspended;
        } 

        $user = $this->createModel($data);

        if (empty($userState) === false){
            foreach($userState as $key => $value){
                $user[$key] = $value;
            }
        }

        $this->validateImportValues($data);

        if (array_key_exists('fields', $data)) {
            $user->setFieldValues($data['fields']);
        }

        $event = $this->onBeforeImport($user, $data);
        if ($event->isValid) {

            // save user
            $result = Craft::$app->getElements()->saveElement($event->element);

            if ($result) {
                $groups = $this->getUserGroupIds($data['groups']);
                Craft::$app->users->assignUserToGroups($user->id, $groups);

                $permissions = MigrationHelper::getPermissionIds($data['permissions']);
                Craft::$app->userPermissions->saveUserPermissions($user->id, $permissions);

                $this->onAfterImport($event->element, $data);
            } else {
                $this->addError('Could not save user: ' . $data['email']);
                foreach ($event->element->getErrors() as $error) {
                    $this->addError(join(',', $error));
                }
                return false;
            }
        } else {
            $this->addError('Error importing ' . $data['handle'] . ' global.');
            $this->addError($event->error);
            return false;
        }

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function createModel(array $data)
    {
        $user = new User();

        if (array_key_exists('id', $data)) {
            $user->id = $data['id'];
        }

        $user->setAttributes($data);

        //need to forcibly set email
        $user->email = $data['email'];

        return $user;
    }

    /**
     * {@inheritdoc}
     */
    protected function getContent(&$content, $element)
    {
        parent::getContent($content, $element);

        $this->getUserGroupHandles($content, $element);
        $this->getUserPermissionHandles($content, $element);
    }

    /**
     * @param array $groups
     *
     * @return array
     */
    private function getUserGroupIds($groups)
    {
        $userGroups = [];

        foreach ($groups as $group) {
            $userGroup = Craft::$app->userGroups->getGroupByHandle($group);
            $userGroups[] = $userGroup->id;
        }

        return $userGroups;
    }

    /**
     * @param array $content
     * @param UserModel $element
     */
    private function getUserGroupHandles(&$content, $element)
    {
        $groups = $element->getGroups();

        $content['groups'] = array();
        foreach ($groups as $group) {
            $content['groups'][] = $group->handle;
        }
    }

    /**
     * @param array $content
     * @param UserModel $element
     */
    private function getUserPermissionHandles(&$content, $element)
    {
        $permissions = Craft::$app->userPermissions->getPermissionsByUserId($element->id);
        $permissions = MigrationHelper::getPermissionHandles($permissions);
        $content['permissions'] = $permissions;
    }
}
