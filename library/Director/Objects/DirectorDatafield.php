<?php

namespace Icinga\Module\Director\Objects;

use Icinga\Module\Director\Core\Json;
use Icinga\Module\Director\Data\Db\DbObjectWithSettings;
use Icinga\Module\Director\Db;
use Icinga\Module\Director\Exception\DuplicateKeyException;
use Icinga\Module\Director\Hook\DataTypeHook;
use Icinga\Module\Director\Web\Form\DirectorObjectForm;
use InvalidArgumentException;
use Zend_Form_Element as ZfElement;

class DirectorDatafield extends DbObjectWithSettings
{
    protected $table = 'director_datafield';

    protected $keyName = 'id';

    protected $autoincKeyName = 'id';

    protected $defaultProperties = array(
        'id'            => null,
        'varname'       => null,
        'caption'       => null,
        'description'   => null,
        'datatype'      => null,
        'format'        => null,
    );

    protected $settingsTable = 'director_datafield_setting';

    protected $settingsRemoteId = 'datafield_id';

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

    /**
     * @return object
     * @throws \Icinga\Exception\NotFoundError
     */
    public function export()
    {
        $plain = (object) $this->getProperties();
        $plain->originalId = $plain->id;
        unset($plain->id);
        $plain->settings = (object) $this->getSettings();

        if (property_exists($plain->settings, 'datalist_id')) {
            $plain->settings->datalist = DirectorDatalist::loadWithAutoIncId(
                $plain->settings->datalist_id,
                $this->getConnection()
            )->get('list_name');
            unset($plain->settings->datalist_id);
        }

        return $plain;
    }

    /**
     * @param $plain
     * @param Db $db
     * @param bool $replace
     * @return DirectorDatafield
     * @throws \Icinga\Exception\NotFoundError
     */
    public static function import($plain, Db $db, $replace = false)
    {
        $properties = (array) $plain;
        if (isset($properties['originalId'])) {
            $id = $properties['originalId'];
            unset($properties['originalId']);
        } else {
            $id = null;
        }

        if (isset($properties['settings']->datalist)) {
            // Just try to load the list, import should fail if missing
            $list = DirectorDatalist::load(
                $properties['settings']->datalist,
                $db
            );
        } else {
            $list = null;
        }

        $encoded = Json::encode($properties);
        if ($id) {
            if (static::exists($id, $db)) {
                $existing = static::loadWithAutoIncId($id, $db);
                $existingProperties = (array) $existing->export();
                unset($existingProperties['originalId']);
                if ($encoded === Json::encode($existingProperties)) {
                    return $existing;
                }
            }
        }

        if ($list) {
            unset($properties['settings']->datalist);
            $properties['settings']->datalist_id = $list->get('id');
        }

        $dba = $db->getDbAdapter();
        $query = $dba->select()
            ->from('director_datafield')
            ->where('varname = ?', $plain->varname);
        $candidates = DirectorDatafield::loadAll($db, $query);

        foreach ($candidates as $candidate) {
            $export = $candidate->export();
            unset($export->originalId);
            if (Json::encode($export) === $encoded) {
                return $candidate;
            }
        }

        return static::create($properties, $db);
    }

    protected function setObject(IcingaObject $object)
    {
        $this->object = $object;
    }

    protected function getObject()
    {
        return $this->object;
    }

    public function getFormElement(DirectorObjectForm $form, $name = null)
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
        if ($object instanceof IcingaObject) {
            if ($object->isTemplate()) {
                $el->setRequired(false);
            }

            $varname = $this->get('varname');

            $inherited = $object->getInheritedVar($varname);

            if (null !== $inherited) {
                $form->setInheritedValue(
                    $el,
                    $inherited,
                    $object->getOriginForVar($varname)
                );
            } elseif ($object->hasRelation('check_command')) {
                // TODO: Move all of this elsewhere and test it
                try {
                    /** @var IcingaCommand $command */
                    $command = $object->getResolvedRelated('check_command');
                    if ($command === null) {
                        return;
                    }
                    $inherited = $command->vars()->get($varname);
                    $inheritedFrom = null;

                    if ($inherited !== null) {
                        $inherited = $inherited->getValue();
                    }

                    if ($inherited === null) {
                        $inherited = $command->getResolvedVar($varname);
                        if ($inherited === null) {
                            $inheritedFrom = $command->getOriginForVar($varname);
                        }
                    } else {
                        $inheritedFrom = $command->getObjectName();
                    }

                    $inherited = $command->getResolvedVar($varname);
                    if (null !== $inherited) {
                        $form->setInheritedValue(
                            $el,
                            $inherited,
                            $inheritedFrom
                        );
                    }
                } catch (\Exception $e) {
                    // Ignore failures
                }
            }
        }
    }
}
