<?php

namespace dgrigg\migrationassistant\services;

/**
 * Interface MigrationManager_IMigrationService
 */
interface IMigrationService
{
    /**
     * @param array $data
     *
     * @return bool
     */
    public function import(array $data);

    /**
     * @param array $data
     *
     * @return array
     */
    public function importItem(array $data);

    /**
     * @param array $ids
     * @param bool  $fullExport
     *
     * @return mixed
     */
    public function export(array $ids, $fullExport = false);

    /**
     * @param int  $id
     * @param bool $fullExport
     *
     * @return mixed
     */
    public function exportItem($id, $fullExport = false);

    /**
     * @param array $data
     *
     * @return mixed
     */
    public function createModel(array $data);


    /**
     * @return string the post field to pull export ids from
     */
    public function getSource();

    /**
     * @return string the property to write export data to
     */
    public function getDestination();
}
