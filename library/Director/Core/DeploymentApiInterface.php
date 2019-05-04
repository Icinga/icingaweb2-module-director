<?php

namespace Icinga\Module\Director\Core;

use Icinga\Module\Director\Db;
use Icinga\Module\Director\IcingaConfig\IcingaConfig;

/**
 * Interface to a deployment API of the monitoring configuration
 *
 * @package Icinga\Module\Director\Core
 */
interface DeploymentApiInterface
{
    /**
     * Collecting log files from the deployment system
     * and write them into the database.
     *
     * @param Db $db
     */
    public function collectLogFiles(Db $db);

    /**
     * Cleanup old stages that are collected and not active
     *
     * @param Db $db
     */
    public function wipeInactiveStages(Db $db);

    /**
     * Returns the active configuration stage
     *
     * @return string
     */
    public function getActiveStageName();

    /**
     * List files in a named stage
     *
     * @param  string  $stage  name of the stage
     * @return string[]
     */
    public function listStageFiles($stage);

    /**
     * Retrieve a raw file from the named stage
     *
     * @param  string  $stage  Stage name
     * @param  string  $file   Relative file path
     *
     * @return string
     */
    public function getStagedFile($stage, $file);

    /**
     * Explicitly delete a stage
     *
     * @param  string  $packageName
     * @param  string  $stageName
     *
     * @return bool
     */
    public function deleteStage($packageName, $stageName);

    /**
     * Deploy the config and activate it
     *
     * @param  IcingaConfig  $config
     * @param  Db            $db
     * @param  string        $packageName
     *
     * @return mixed
     */
    public function dumpConfig(IcingaConfig $config, Db $db, $packageName = null);
}
