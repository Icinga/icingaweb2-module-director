<?php

namespace Icinga\Module\Director\Data;

use Icinga\Module\Director\Data\Db\DbObject;
use Icinga\Module\Director\Objects\DirectorJob;
use Icinga\Module\Director\Objects\ImportRowModifier;
use Icinga\Module\Director\Objects\ImportSource;
use Icinga\Module\Director\Objects\SyncRule;

class ImportExportDeniedProperties
{
    protected static $denyProperties = [
        DirectorJob::class => [
            'last_attempt_succeeded',
            'last_error_message',
            'ts_last_attempt',
            'ts_last_error',
        ],
        ImportSource::class => [
            // No state export
            'import_state',
            'last_error_message',
            'last_attempt',
        ],
        ImportRowModifier::class => [
            // Not state, but to be removed:
            'source_id',
        ],
        SyncRule::class => [
            'sync_state',
            'last_error_message',
            'last_attempt',
        ],
    ];

    public static function strip(array &$props, DbObject $object, $showIds = false)
    {
        // TODO: this used to exist. Double-check all imports to verify it's not in use
        // $originalId = $props['id'];

        if (! $showIds) {
            unset($props['id']);
        }
        $class = get_class($object);
        if (isset(self::$denyProperties[$class])) {
            foreach (self::$denyProperties[$class] as $key) {
                unset($props[$key]);
            }
        }
    }
}
