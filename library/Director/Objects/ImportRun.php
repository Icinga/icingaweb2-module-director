<?php

namespace Icinga\Module\Director\Objects;

use Icinga\Module\Director\Data\Db\DbObject;

class ImportRun extends DbObject
{
    protected $table = 'import_run';

    protected $keyName = 'id';

    protected $autoincKeyName = 'id';

    protected $defaultProperties = array(
        'id'              => null,
        'source_id'       => null,
        'rowset_checksum' => null,
        'start_time'      => null,
        'end_time'        => null,
        // TODO: Check whether succeeded could be dropped
        'succeeded'       => null,
    );

    public function prepareImportedObjectQuery($columns = array('object_name'))
    {
        return $this->getDb()->select()->from(
            array('r' => 'imported_row'),
            $columns
        )->joinLeft(
            array('rsr' => 'rowset_checksum'),
            'rsr.row_checksum = r.checksum',
            'r.object_name'
        )->where(
            'rsr.rowset_checksum = ?',
            $this->getConnection()->quoteBinary($this->rowset_checksum)
        );
    }
}
