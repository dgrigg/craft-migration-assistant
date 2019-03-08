<?php

namespace dgrigg\migrationassistant\variables;
use dgrigg\migrationassistant\helpers\MigrationManagerHelper;

/**
 * Deploy Variable provides access to database objects from templates
 */
class MigrationManagerVariable
{
    /**
     * @return boolean
     */
    public function isVersion($version){
        return MigrationManagerHelper::isVersion($version);
    }
}
