<?php

namespace Icinga\Module\Director\Objects;

use Icinga\Module\Director\Data\Db\DbObject;

class DirectorDatalist extends DbObject
{
    protected $table = 'director_datalist';

    protected $keyName = 'id';

    protected $autoincKeyName = 'id';

    protected $defaultProperties = array(
        'id'            => null,
        'list_name'     => null,
        'owner'         => null
    );

    public function export()
    {
        $plain = (object) $this->getProperties();
        $plain->originalId = $plain->id;
        unset($plain->id);

        $plain->entries = [];
        $entries = DirectorDatalistEntry::loadAllForList($this);
        foreach ($entries as $key => $entry) {
            $plainEntry = (object) $entry->getProperties();
            unset($plainEntry->id);
            unset($plainEntry->list_id);

            $plain->entries[] = $plainEntry;
        }

        return $plain;
    }
}
