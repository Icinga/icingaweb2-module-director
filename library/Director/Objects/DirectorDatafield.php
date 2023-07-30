<?php

namespace Icinga\Module\Director\Objects;

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

class DirectorDatafield extends DbObjectWithSettings
{
    protected $table = 'director_datafield';
    protected $keyName = 'id';
    protected $autoincKeyName = 'id';
    protected $uuidColumn = 'uuid';
    protected $settingsTable = 'director_datafield_setting';
    protected $settingsRemoteId = 'datafield_id';

    protected $defaultProperties = [
        'id'            => null,
        'uuid'          => null,
        'category_id'   => null,
        'varname'       => null,
        'caption'       => null,
        'description'   => null,
        'datatype'      => null,
        'format'        => null,
    ];

    protected $relations = [
        'category' => 'DirectorDatafieldCategory'
    ];

    /** @var ?DirectorDatafieldCategory */
    private $category;
    private $object;

    public static function fromDbRow($row, Db $connection)
    {
        $obj = static::create((array) $row, $connection);
        $obj->loadedFromDb = true;
        // TODO: $obj->setUnmodified();
        $obj->hasBeenModified = false;
        $obj->modifiedProperties = array();
        $settings = $obj->getSettings();
        // TODO: eventually prefetch
        $obj->onLoadFromDb();

        // Restoring values eventually destroyed by onLoadFromDb
        foreach ($settings as $key => $value) {
            $obj->settings[$key] = $value;
        }

        return $obj;
    }

    public function hasCategory()
    {
        return $this->category !== null || $this->get('category_id') !== null;
    }

    /**
     * @throws \Icinga\Exception\NotFoundError
     */
    public function getCategory(): ?DirectorDatafieldCategory
    {
        if ($this->category) {
            return $this->category;
        } elseif ($id = $this->get('category_id')) {
            return DirectorDatafieldCategory::loadWithAutoIncId($id, $this->getConnection());
        } else {
            return null;
        }
    }

    public function getCategoryName(): ?string
    {
        $category = $this->getCategory();
        if ($category === null) {
            return null;
        } else {
            return $category->get('category_name');
        }
    }

    public function setCategory($category)
    {
        if ($category === null) {
            $this->category = null;
            $this->set('category_id', null);
        } elseif ($category instanceof DirectorDatafieldCategory) {
            if ($category->hasBeenLoadedFromDb()) {
                $this->set('category_id', $category->get('id'));
            }
            $this->category = $category;
        } else {
            if ($category = DirectorDatafieldCategory::loadOptional($category, $this->getConnection())) {
                $this->setCategory($category);
            } else {
                $this->setCategory(DirectorDatafieldCategory::create([
                    'category_name' => $category
                ], $this->getConnection()));
            }
        }
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
        $plain->settings = (object) $this->getSettings();

        if (property_exists($plain->settings, 'datalist_id')) {
            $plain->settings->datalist = DirectorDatalist::loadWithAutoIncId(
                $plain->settings->datalist_id,
                $this->getConnection()
            )->get('list_name');
            unset($plain->settings->datalist_id);
        }
        if (property_exists($plain, 'category_id')) {
            $plain->category = $this->getCategoryName();
            unset($plain->category_id);
        }

        return $plain;
    }

    /**
     * @throws \Icinga\Exception\NotFoundError
     */
    public static function import(stdClass $plain, Db $db): DirectorDatafield
    {
        $dba = $db->getDbAdapter();
        if ($uuid = $plain->uuid ?? null) {
            $uuid = Uuid::fromString($uuid);
            if ($candidate = DirectorDatafield::loadWithUniqueId($uuid, $db)) {
                BasketSnapshotFieldResolver::fixOptionalDatalistReference($plain, $db);
                assert($candidate instanceof DirectorDatafield);
                $candidate->setProperties((array) $plain);
                return $candidate;
            }
        }
        $query = $dba->select()->from('director_datafield')->where('varname = ?', $plain->varname);
        $candidates = DirectorDatafield::loadAll($db, $query);

        foreach ($candidates as $candidate) {
            $export = $candidate->export();
            CompareBasketObject::normalize($export);
            unset($export->uuid);
            unset($plain->originalId);
            if (CompareBasketObject::equals($export, $plain)) {
                return $candidate;
            }
        }
        BasketSnapshotFieldResolver::fixOptionalDatalistReference($plain, $db);

        return static::create((array) $plain, $db);
    }

    protected function beforeStore()
    {
        if ($this->category) {
            if (!$this->category->hasBeenLoadedFromDb()) {
                throw new \RuntimeException('Trying to store a datafield with an unstored Category');
            }
            $this->set('category_id', $this->category->get('id'));
        }
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
        $className = $this->get('datatype');

        if ($name === null) {
            $name = 'var_' . $this->get('varname');
        }

        if (! class_exists($className)) {
            $form->addElement('text', $name, array('disabled' => 'disabled'));
            $el = $form->getElement($name);
            $msg = $form->translate('Form element could not be created, %s is missing');
            $el->addError(sprintf($msg, $className));
            return $el;
        }

        /** @var DataTypeHook $dataType */
        $dataType = new $className;
        $dataType->setSettings($this->getSettings());
        $el = $dataType->getFormElement($name, $form);

        if ($this->getSetting('icinga_type') !== 'command'
            && $this->getSetting('is_required') === 'y'
        ) {
            $el->setRequired(true);
        }
        if ($caption = $this->get('caption')) {
            $el->setLabel($caption);
        }

        if ($description = $this->get('description')) {
            $el->setDescription($description);
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

        $varName = $this->get('varname');
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
                list($inherited, $origin) = $cmd;
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
