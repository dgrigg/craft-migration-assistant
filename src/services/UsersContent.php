<?php

namespace dgrigg\migrationassistant\services;

use Craft;
use craft\elements\Address;
use craft\elements\User;
use craft\base\Element;
use craft\helpers\ElementHelper as CraftElementHelper;
use dgrigg\migrationassistant\helpers\ElementHelper;
use Exception;

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

        $this->addManifest($user->username);

        if ($user) {
            $validAttributes = [
                'admin',
                'username',
                'email',
                'fullName'
            ];
            $attributes = $user->getAttributes();

            foreach($attributes as $key => $value){
                if (in_array($key, $validAttributes) == false){
                    unset($attributes[$key]);
                }
            }           

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

                $permissions = ElementHelper::getPermissionIds($data['permissions']);
                Craft::$app->userPermissions->saveUserPermissions($user->id, $permissions);

                //photo
                $this->setPhoto($data, $user);
                
                //addresses
                $this->setAddresses($data, $user);

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

        //die('imported');

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
    public function getContent(&$content, $element)
    {
        parent::getContent($content, $element);
        $this->getPhoto($content, $element);
        $this->getAddresses($content, $element);
        $this->getUserGroupHandles($content, $element);
        $this->getUserPermissionHandles($content, $element);
    }

    /**
     * @param array $content
     * @param UserModel $element
     */
    private function getPhoto(&$content, $element)
    {
        if (!is_null($element->photo)){
            $content['photo'] = ElementHelper::getSourceHandle($element->photo);
        }
    }

    /**
     * @param array $data
     * @param UserModel $element
     */
    private function setPhoto($data , $element)
    {
        if (array_key_exists('photo', $data) && !is_null($data['photo'])){
            $asset = [$data['photo']];
            ElementHelper::populateIds($asset);       
            if (count($asset) > 0){          
                $photo = Craft::$app->getAssets()->getAssetById($asset[0]);
                if ($photo){
                    $element->setPhoto($photo);
                    Craft::$app->getElements()->saveElement($element, false);
                }
            }
        }
    }

    /**
     * @param array $content
     * @param UserModel $element
     */
    private function getAddresses(&$content, $element)
    {
        $addresses= $element->getAddresses();
        $content['addresses'] = [];

        foreach ($addresses as $address) {
            $addressContent = array();

            $fields = Craft::$app->addresses->getUsedFields($address->countryCode);
            $addressContent['slug'] = $address->slug;
            $addressContent['countryCode'] = $address->countryCode;
            $addressContent['title'] = $address->title;
            $addressContent['fields'] = [];
            foreach($fields as $field){
                $addressContent[$field] = $address[$field];
            }
           
            foreach ($address->getFieldLayout()->getCustomFields() as $field) {
                $this->getFieldContent($addressContent['fields'], $field, $address);

            }
            $content['addresses'][] = $addressContent;
        }      
    }

    /**
     * @param array $content
     * @param UserModel $element
     */
    private function setAddresses(&$data, $element)
    {
        if (array_key_exists('addresses', $data) && !is_null($data['addresses'])){
            foreach($data['addresses'] as $addressContent){

                $query = Craft::$app->elements->createElementQuery(Address::class);
                $query->slug = $addressContent['slug'];

                $address = $query->one();
                if (!$address){
                    $address = new Address();
                    $address->ownerId = $element->id;
                    $address->slug = $addressContent['slug'];
                }
                
                $address['title'] = $addressContent['title'];
                $fields = Craft::$app->addresses->getUsedFields($addressContent['countryCode']);
                foreach($fields as $field){
                    if (key_exists($field, $addressContent) && !is_null($addressContent[$field])) {
                        $address[$field] = $addressContent[$field];
                    }
                }

                $address->setFieldValues($addressContent['fields']);

                try {
                    $result = Craft::$app->getElements()->saveElement($address, true, true);
                
                } catch (Exception $error){
                    Craft::error($error, __METHOD__);
                    $this->addError('Failed to save user address');
                }    
            }
        }
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
        $permissions = ElementHelper::getPermissionHandles($permissions);
        $content['permissions'] = $permissions;
    }
}
