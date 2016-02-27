<?php

namespace Icinga\Module\Director\Objects;

use Icinga\Module\Director\Data\Db\DbObject;

class DirectorDeploymentLog extends DbObject
{
    protected $table = 'director_deployment_log';

    protected $keyName = 'id';

    protected $autoincKeyName = 'id';

    protected $defaultProperties = array(
        'id'                     => null,
        'config_checksum'        => null,
        'last_activity_checksum' => null,
        'peer_identity'          => null,
        'start_time'             => null,
        'end_time'               => null,
        'abort_time'             => null,
        'duration_connection'    => null,
        'duration_dump'          => null,
        'stage_name'             => null,
        'stage_collected'        => null,
        'connection_succeeded'   => null,
        'dump_succeeded'         => null,
        'startup_succeeded'      => null,
        'username'               => null,
        'startup_log'            => null,
    );
}
