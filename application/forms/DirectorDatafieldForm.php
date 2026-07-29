<?php

namespace Icinga\Module\Director\Forms;

use Icinga\Exception\ConfigurationError;
use Icinga\Module\Director\CustomVariable\CustomVariables;
use Icinga\Module\Director\Hook\DataTypeHook;
use Icinga\Module\Director\Web\Form\DirectorObjectForm;
use Icinga\Application\Hook;
use Exception;

class DirectorDatafieldForm extends DirectorObjectForm
{
    protected $objectName = 'Data field';

    protected $listUrl = 'director/data/fields';

    protected function onRequest()
    {
        if ($this->hasBeenSent()) {
            if ($this->shouldBeDeleted()) {
                $varname = $this->getSentValue('varname');
                if ($cnt = CustomVariables::countAll($varname, $this->getDb())) {
                    $this->askForVariableDeletion($varname, $cnt);
                }
            } elseif ($this->shouldBeRenamed()) {
                $varname = $this->object()->getOriginalProperty('varname');
                if ($cnt = CustomVariables::countAll($varname, $this->getDb())) {
                    $this->askForVariableRename(
                        $varname,
                        $this->getSentValue('varname'),
                        $cnt
                    );
                }
            }
        }

        parent::onRequest();
    }

    protected function askForVariableDeletion($varname, $cnt)
    {
        $msg = $this->translate(
            'Leaving custom variables in place while removing the related field is'
            . ' perfectly legal and might be a desired operation. This way you can'
            . ' no longer modify related custom variables in the Director GUI, but'
            . ' the variables themselves will stay there and continue to be deployed.'
            . ' When you re-add a field for the same variable later on, everything'
            . ' will continue to work as before'
        );

        $this->addBoolean('wipe_vars', array(
            'label'       => $this->translate('Wipe related vars'),
            'description' => sprintf($msg, $this->getSentValue('varname')),
            'required'    => true,
        ));

        if ($wipe = $this->getSentValue('wipe_vars')) {
            if ($wipe === 'y') {
                CustomVariables::deleteAll($varname, $this->getDb());
            }
        } else {
            $this->abortDeletion();
            $this->addError(
                sprintf(
                    $this->translate('Also wipe all "%s" custom variables from %d objects?'),
                    $varname,
                    $cnt
                )
            );
            $this->getElement('wipe_vars')->addError(
                sprintf(
                    $this->translate(
                        'There are %d objects with a related property. Should I also'
                        . ' remove the "%s" property from them?'
                    ),
                    $cnt,
                    $varname
                )
            );
        }
    }

    protected function askForVariableRename($oldname, $newname, $cnt)
    {
        $msg = $this->translate(
            'Leaving custom variables in place while renaming the related field is'
            . ' perfectly legal and might be a desired operation. This way you can'
            . ' no longer modify related custom variables in the Director GUI, but'
            . ' the variables themselves will stay there and continue to be deployed.'
            . ' When you re-add a field for the same variable later on, everything'
            . ' will continue to work as before'
        );

        $this->addBoolean('rename_vars', array(
            'label'       => $this->translate('Rename related vars'),
            'description' => sprintf($msg, $this->getSentValue('varname')),
            'required'    => true,
        ));

        if ($wipe = $this->getSentValue('rename_vars')) {
            if ($wipe === 'y') {
                CustomVariables::renameAll($oldname, $newname, $this->getDb());
            }
        } else {
            $this->abortDeletion();
            $this->addError(
                sprintf(
                    $this->translate('Also rename all "%s" custom variables to "%s" on %d objects?'),
                    $oldname,
                    $newname,
                    $cnt
                )
            );
            $this->getElement('rename_vars')->addError(
                sprintf(
                    $this->translate(
                        'There are %d objects with a related property. Should I also'
                        . ' rename the "%s" property to "%s" on them?'
                    ),
                    $cnt,
                    $oldname,
                    $newname
                )
            );
        }
    }

    public function setup()
    {
        $this->addHtmlHint(
            $this->translate(
                'Data fields allow you to customize input controls for Icinga custom'
                . ' variables. Once you defined them here, you can provide them through'
                . ' your defined templates. This gives you a granular control over what'
                . ' properties your users should be allowed to configure in which way.'
            )
        );

        $this->addElement('text', 'varname', array(
            'label'       => $this->translate('Field name'),
            'description' => $this->translate(
                'This will be the name of the custom variable in the rendered Icinga configuration.'
            ),
            'required'    => true,
        ));

        $this->addElement('text', 'caption', array(
            'label'       => $this->translate('Caption'),
            'required'    => true,
            'description' => $this->translate(
                'The caption which should be displayed to your users when this field'
                . ' is shown'
            )
        ));

        $this->addElement('textarea', 'description', array(
            'label'       => $this->translate('Description'),
            'description' => $this->translate(
                'An extended description for this field. Will be shown as soon as a'
                . ' user puts the focus on this field'
            ),
            'rows'        => '3',
        ));

        $this->addElement('select', 'category_id', [
            'label' => $this->translate('Data Field Category'),
            'multiOptions'  => $this->optionalEnum($this->enumCategories()),
        ]);

        $error = false;
        try {
            $types = $this->enumDataTypes();
        } catch (Exception $e) {
            $error = $e->getMessage();
            $types = $this->optionalEnum(array());
        }

        $this->addElement('select', 'datatype', array(
            'label'         => $this->translate('Data type'),
            'description'   => $this->translate('Field type'),
            'required'      => true,
            'multiOptions'  => $types,
            'class'         => 'autosubmit',
        ));
        if ($error) {
            $this->getElement('datatype')->addError($error);
        }

        $object = $this->object();
        try {
            if ($class = $this->getSentValue('datatype')) {
                if ($class && array_key_exists($class, $types)) {
                    $this->addSettings($class);
                }
            } elseif ($class = $object->get('datatype')) {
                $this->addSettings($class);
            }

            // TODO: next line looks like obsolete duplicate code to me
            $this->addSettings();
        } catch (Exception $e) {
            $this->getElement('datatype')->addError($e->getMessage());
        }

        foreach ($object->getSettings() as $key => $val) {
            if ($el = $this->getElement($key)) {
                $el->setValue($val);
            }
        }

        $this->setButtons();
    }

    public function shouldBeRenamed()
    {
        $object = $this->object();
        return $object->hasBeenLoadedFromDb()
            && $object->getOriginalProperty('varname') !== $this->getSentValue('varname');
    }

    protected function addSettings($class = null)
    {
        if ($class === null) {
            $class = $this->getValue('datatype');
        }

        if ($class !== null) {
            if (! class_exists($class)) {
                throw new ConfigurationError(
                    'The hooked class "%s" for this data field does no longer exist',
                    $class
                );
            }

            $class::addSettingsFormFields($this);
        }
    }

    protected function clearOutdatedSettings()
    {
        $names = array();
        $object = $this->object();
        $global = array('varname', 'description', 'caption', 'datatype');

        /** @var \Zend_Form_Element $el */
        foreach ($this->getElements() as $el) {
            if ($el->getIgnore()) {
                continue;
            }

            $name = $el->getName();
            if (in_array($name, $global)) {
                continue;
            }

            $names[$name] = $name;
        }


        foreach ($object->getSettings() as $setting => $value) {
            if (! array_key_exists($setting, $names)) {
                unset($object->$setting);
            }
        }
    }

    public function onSuccess()
    {
        $this->clearOutdatedSettings();

        if ($class = $this->getValue('datatype')) {
            if (array_key_exists($class, $this->enumDataTypes())) {
                $this->addHidden('format', $class::getFormat());
            }
        }

        parent::onSuccess();
    }

    protected function enumDataTypes()
    {
        $hooks = Hook::all('Director\\DataType');
        $enum = ['' => $this->translate('- please choose -')];
        /** @var DataTypeHook $hook */
        foreach ($hooks as $hook) {
            $enum[get_class($hook)] = $hook->getName();
        }

        return $enum;
    }

    protected function enumCategories()
    {
        $db = $this->getDb()->getDbAdapter();
        return $db->fetchPairs(
            $db->select()->from('director_datafield_category', ['id', 'category_name'])
        );
    }
}
