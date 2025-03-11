<?php

namespace Icinga\Module\Director\Objects;

use Icinga\Module\Director\Data\Db\DbObject;
use Icinga\Module\Director\Data\Db\DbObjectWithSettings;
use Icinga\Module\Director\Db;
use Icinga\Module\Director\DirectorObject\Automation\BasketSnapshotFieldResolver;
use Icinga\Module\Director\DirectorObject\Automation\CompareBasketObject;
use Icinga\Module\Director\Forms\IcingaServiceForm;
use Icinga\Module\Director\Hook\DataTypeHook;
use Icinga\Module\Director\Resolver\OverriddenVarsResolver;
use Icinga\Module\Director\Web\Form\DirectorObjectForm;
use Ramsey\Uuid\Uuid;
use stdClass;
use Zend_Form_Element as ZfElement;

class DirectorDictionary extends DbObject
{
    protected $table = 'director_dictionary';

    protected $keyName = 'uuid';

    protected $uuidColumn = 'uuid';

    protected $defaultProperties = [
        'uuid'          => null,
        'parent_uuid'   => null,
        'name'          => null,
        'label'         => null,
        'type'          => null
    ];

    protected $relations = [
        'category' => 'DirectorDatafieldCategory'
    ];

    private $object;

    public static function fromDbRow($row, Db $connection)
    {
        $obj = static::create((array) $row, $connection);
        $obj->loadedFromDb = true;
        // TODO: $obj->setUnmodified();
        $obj->hasBeenModified = false;
        $obj->modifiedProperties = array();
        // TODO: eventually prefetch
        $obj->onLoadFromDb();

        return $obj;
    }

    /**
     * @throws \Icinga\Exception\NotFoundError
     */
    public function export(): stdClass
    {
        $plain = (object) $this->getProperties();
        unset($plain->id);
        if ($uuid = $this->get('uuid')) {
            $plain->uuid = Uuid::fromBytes($uuid)->toString();
        }

        return $plain;
    }

    /**
     * @throws \Icinga\Exception\NotFoundError
     */
    public static function import(stdClass $plain, Db $db): DirectorDictionary
    {
        $dba = $db->getDbAdapter();
        if ($uuid = $plain->uuid ?? null) {
            $uuid = Uuid::fromString($uuid);
            if ($candidate = DirectorDictionary::loadWithUniqueId($uuid, $db)) {
                assert($candidate instanceof DirectorDictionary);
                $candidate->setProperties((array) $plain);
                return $candidate;
            }
        }
        $query = $dba->select()->from('director_dictionary')->where('name = ?', $plain->name);
        $candidates = DirectorDictionary::loadAll($db, $query);

        foreach ($candidates as $candidate) {
            $export = $candidate->export();
            CompareBasketObject::normalize($export);
            unset($export->uuid);
            unset($plain->originalId);
            if (CompareBasketObject::equals($export, $plain)) {
                return $candidate;
            }
        }

        return static::create((array) $plain, $db);
    }

    protected function setObject(IcingaObject $object)
    {
        $this->object = $object;
    }

    protected function getObject()
    {
        return $this->object;
    }
}
