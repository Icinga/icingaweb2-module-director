<?php

namespace Icinga\Module\Director\Objects;

use Icinga\Module\Director\Data\Db\DbObjectWithSettings;
use Icinga\Module\Director\Db;
use Icinga\Module\Director\Hook\DataTypeHook;
use Icinga\Module\Director\Web\Form\DirectorObjectForm;
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
