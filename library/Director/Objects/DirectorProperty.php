<?php

namespace Icinga\Module\Director\Objects;

use Icinga\Module\Director\Data\Db\DbObject;
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

class DirectorProperty extends DbObject
{
    protected $table = 'director_property';
    protected $keyName = 'uuid';
    protected $uuidColumn = 'uuid';

    protected $defaultProperties = [
        'uuid'          => null,
        'parent_uuid'   => null,
        'key_name'      => null,
        'label'         => null,
        'value_type'    => null,
        'instantiable'  => null
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
        if ($uuid = $this->get('uuid')) {
            $plain->uuid = Uuid::fromBytes($uuid)->toString();
        }

        return $plain;
    }

    /**
     * @throws \Icinga\Exception\NotFoundError
     */
    public static function import(stdClass $plain, Db $db): DirectorProperty
    {
        $dba = $db->getDbAdapter();
        if ($uuid = $plain->uuid ?? null) {
            $uuid = Uuid::fromString($uuid);
            if ($candidate = DirectorProperty::loadWithUniqueId($uuid, $db)) {
                BasketSnapshotFieldResolver::fixOptionalDatalistReference($plain, $db);
                assert($candidate instanceof DirectorProperty);
                $candidate->setProperties((array) $plain);
                return $candidate;
            }
        }
        $query = $dba->select()->from('director_property')->where('key_name = ?', $plain->key_name);
        $candidates = DirectorProperty::loadAll($db, $query);

        foreach ($candidates as $candidate) {
            $export = $candidate->export();
            CompareBasketObject::normalize($export);
            unset($export->uuid);
            if (CompareBasketObject::equals($export, $plain)) {
                return $candidate;
            }
        }
        BasketSnapshotFieldResolver::fixOptionalDatalistReference($plain, $db);

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

    public function getFormElement(DirectorObjectForm $form, $name = null): ?ZfElement
    {
        $className = $this->get('value_type');

        if ($name === null) {
            $name = 'var_' . $this->get('key_name');
        }

        if (! class_exists($className)) {
            $form->addElement('text', $name, array('disabled' => 'disabled'));
            $el = $form->getElement($name);
            $msg = $form->translate('Form element could not be created, %s is missing');
            $el->addError(sprintf($msg, $className));
            return $el;
        }

        /** @var DataTypeHook $dataType */
        $dataType = new $className();
        $el = $dataType->getFormElement($name, $form);
        if ($caption = $this->get('label')) {
            $el->setLabel($caption);
        }

        $this->applyObjectData($el, $form);

        return $el;
    }

    protected function applyObjectData(ZfElement $el, DirectorObjectForm $form)
    {
        $object = $form->getObject();
        if (! ($object instanceof IcingaObject)) {
            return;
        }
        if ($object->isTemplate()) {
            $el->setRequired(false);
        }

        $varName = $this->get('key_name');
        $inherited = $origin = null;

        if ($form instanceof IcingaServiceForm && $form->providesOverrides()) {
            $resolver = new OverriddenVarsResolver($form->getDb());
            $vars = $resolver->fetchForServiceName($form->getHost(), $object->getObjectName());
            foreach ($vars as $host => $values) {
                if (\property_exists($values, $varName)) {
                    $inherited = $values->$varName;
                    $origin = $host;
                }
            }
        }

        if ($inherited === null) {
            $inherited = $object->getInheritedVar($varName);
            if (null !== $inherited) {
                $origin = $object->getOriginForVar($varName);
            }
        }

        if ($inherited === null) {
            $cmd = $this->eventuallyGetResolvedCommandVar($object, $varName);
            if ($cmd !== null) {
                [$inherited, $origin] = $cmd;
            }
        }

        if ($inherited !== null) {
            $form->setInheritedValue($el, $inherited, $origin);
        }
    }

    protected function eventuallyGetResolvedCommandVar(IcingaObject $object, $varName): ?array
    {
        if (! $object->hasRelation('check_command')) {
            return null;
        }

        // TODO: Move all of this elsewhere and test it
        try {
            /** @var IcingaCommand $command */
            $command = $object->getResolvedRelated('check_command');
            if ($command === null) {
                return null;
            }
            $inherited = $command->vars()->get($varName);
            $inheritedFrom = null;

            if ($inherited !== null) {
                $inherited = $inherited->getValue();
            }

            if ($inherited === null) {
                $inherited = $command->getResolvedVar($varName);
                if ($inherited === null) {
                    $inheritedFrom = $command->getOriginForVar($varName);
                }
            } else {
                $inheritedFrom = $command->getObjectName();
            }

            $inherited = $command->getResolvedVar($varName);

            return [$inherited, $inheritedFrom];
        } catch (\Exception $e) {
            return null;
        }
    }
}
