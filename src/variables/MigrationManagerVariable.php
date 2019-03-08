<?php

namespace dgrigg\migrationmanagerpro\variables;
use dgrigg\migrationmanagerpro\helpers\MigrationManagerHelper;

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
