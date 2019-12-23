<?php

namespace dgrigg\migrationassistant\actions;
use dgrigg\migrationassistant\MigrationAssistant;
use Craft;
use craft\base\ElementAction;
use craft\elements\db\ElementQueryInterface;


class MigrateAssetElementAction extends ElementAction
{


    /**
     * @inheritdoc
     */
    public function getTriggerLabel(): string
    {
        return Craft::t('app', 'Create Migration');
    }


    /**
     * {@inheritdoc}
     */
    public function performAction(ElementQueryInterface $query): bool
    {
        $params['asset'] = [];
        $elements = $query->all();

        foreach ($elements as $element) {
            $params['asset'][] = $element->id;
        }

        if (MigrationAssistant::getInstance()->migrations->createContentMigration($params)) {

            $this->setMessage(Craft::t('app', 'Migration created.'));
            return true;
        } else {

            $this->setMessage(Craft::t('app', 'Migration could not be created.'));
            return false;
        }
    }
}
