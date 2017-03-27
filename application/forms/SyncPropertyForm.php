<?php

namespace Icinga\Module\Director\Forms;

use Exception;
use Icinga\Module\Director\Hook\ImportSourceHook;
use Icinga\Module\Director\Objects\SyncProperty;
use Icinga\Module\Director\Objects\SyncRule;
use Icinga\Module\Director\Objects\IcingaObject;
use Icinga\Module\Director\Objects\ImportSource;
use Icinga\Module\Director\Web\Form\DirectorObjectForm;

class SyncPropertyForm extends DirectorObjectForm
{
    /**
     * @var SyncRule
     */
    private $rule;

    /** @var ImportSource */
    private $importSource;

    /** @var ImportSourceHook */
    private $importSourceHook;

    private $dummyObject;

    const EXPRESSION = '__EXPRESSION__';

    /**
     * @throws \Zend_Form_Exception
     */
    public function setup()
    {
        $this->addHidden('rule_id', $this->rule->get('id'));

        $this->addElement('select', 'source_id', array(
            'label'        => $this->translate('Source Name'),
            'multiOptions' => $this->enumImportSource(),
            'required'     => true,
            'class'        => 'autosubmit',
        ));
        if (! $this->hasObject() && ! $this->getSentValue('source_id')) {
            return;
        }

        $this->addElement('select', 'destination_field', array(
            'label'        => $this->translate('Destination Field'),
            'multiOptions' => $this->optionalEnum($this->listDestinationFields()),
            'required'     => true,
            'class'        => 'autosubmit',
        ));

        if ($this->getSentValue('destination_field')) {
            $destination = $this->getSentValue('destination_field');
        } elseif ($this->hasObject()) {
            $destination = $this->getObject()->destination_field;
        } else {
            return;
        }

        $isCustomvar = substr($destination, 0, 5) === 'vars.';

        if ($isCustomvar) {
            $varname = substr($destination, 5);
            $this->addElement('text', 'customvar', array(
                'label'    => $this->translate('Custom variable'),
                'required' => true,
                'ignore'   => true,
            ));

            if ($varname !== '*') {
                $this->setElementValue('destination_field', 'vars.*');
                $this->setElementValue('customvar', $varname);
                if ($this->hasObject()) {
                    $this->getObject()->destination_field = 'vars.*';
                }
            }
        }

        $this->addSourceColumnElement($destination);

        $this->addElement('YesNo', 'use_filter', array(
            'label'        => $this->translate('Set based on filter'),
            'ignore'       => true,
            'class'        => 'autosubmit',
            'required'     => true,
        ));

        if ($this->hasBeenSent()) {
            $useFilter = $this->getSentValue('use_filter');
            if ($useFilter === null) {
                $this->setElementValue('use_filter', $useFilter = 'n');
            }
        } else {
            $useFilter = strlen($this->getObject()->filter_expression) ? 'y' : 'n';
            $this->setElementValue('use_filter', $useFilter);
        }

        if ($useFilter === 'y') {
            $this->addElement('text', 'filter_expression', array(
                'label'       => $this->translate('Filter Expression'),
                'description' => $this->translate(
                    'This allows to filter for specific parts within the given source expression.'
                    . ' You are allowed to refer all imported columns. Examples: host=www* would'
                    . ' set this property only for rows imported with a host property starting'
                    . ' with "www". Complex example: host=www*&!(address=127.*|address6=::1)'
                ),
                'required'    => true,
                // TODO: validate filter
            ));
        }

        if ($isCustomvar || $destination === 'vars') {
            $this->addElement('select', 'merge_policy', array(
                'label'        => $this->translate('Merge Policy'),
                'description'  => $this->translate(
                    'Whether you want to merge or replace the destination field.'
                    . ' Makes no difference for strings'
                ),
                'required'     => true,
                'multiOptions' => $this->optionalEnum(array(
                    'merge'    => 'merge',
                    'override' => 'replace'
                ))
            ));
        } else {
            $this->addHidden('merge_policy', 'override');
        }

        $this->setButtons();
    }

    protected function hasSubOption($options, $key)
    {
        foreach ($options as $mainKey => $sub) {
            if (! is_array($sub)) {
                // null -> please choose - or similar
                continue;
            }

            if (array_key_exists($key, $sub)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param $destination
     * @return $this
     * @throws \Zend_Form_Exception
     */
    protected function addSourceColumnElement($destination)
    {
        $error = false;

        $srcTitle = $this->translate('Source columns');
        try {
            $columns[$srcTitle] = $this->listSourceColumns();
            natsort($columns[$srcTitle]);
        } catch (Exception $e) {
            $srcTitle .= sprintf(' (%s)', $this->translate('failed to fetch'));
            $columns[$srcTitle] = array();
            $error = sprintf(
                $this->translate('Unable to fetch data: %s'),
                $e->getMessage()
            );
        }

        if ($destination === 'import') {
            $this->addIcingaTempateColumns($columns);
        } elseif ($destination === 'list_id') {
            $this->addDatalistsColumns($columns);
        }

        $xpTitle = $this->translate('Expert mode');
        $columns[$xpTitle][self::EXPRESSION] = $this->translate('Custom expression');

        $this->addElement('select', 'source_column', array(
            'label'        => $this->translate('Source Column'),
            'multiOptions' => $this->optionalEnum($columns),
            'required'     => true,
            'ignore'       => true,
            'class'        => 'autosubmit',
        ));

        if ($error) {
            $this->getElement('source_column')->addError($error);
        }

        $showExpression = false;

        if ($this->hasBeenSent()) {
            $sentValue = $this->getSentValue('source_column');
            if ($sentValue === self::EXPRESSION) {
                $showExpression = true;
            }
        } elseif ($this->hasObject()) {
            $objectValue = $this->getObject()->source_expression;
            if ($this->hasSubOption($columns, $objectValue)) {
                $this->setElementValue('source_column', $objectValue);
            } else {
                $this->setElementValue('source_column', self::EXPRESSION);
                $showExpression = true;
            }
        }

        if ($showExpression) {
            $this->addElement('text', 'source_expression', array(
                'label'       => $this->translate('Source Expression'),
                'description' => $this->translate(
                    'A custom string. Might contain source columns, please use placeholders'
                    . ' of the form ${columnName} in such case.  Structured data sources'
                    . ' can be referenced as ${columnName.sub.key}'
                ),
                'required'    => true,
            ));
        }


        return $this;
    }

    protected function addIcingaTempateColumns(& $columns)
    {
        $funcTemplates = 'enum' . ucfirst($this->rule->get('object_type')) . 'Templates';
        if (method_exists($this->db, $funcTemplates)) {
            $templates = $this->db->$funcTemplates();
            if (! empty($templates)) {
                $templates = array_combine($templates, $templates);
            }

            $title = $this->translate('Existing templates');
            $columns[$title] = $templates;
            natsort($columns[$title]);
        }
    }

    protected function addDatalistsColumns(& $columns)
    {
        // Clear other columns, we don't allow them right now
        $columns = [];
        $db = $this->db->getDbAdapter();
        $enum = $db->fetchPairs(
            $db->select()->from('director_datalist', ['id', 'list_name'])->order('list_name')
        );

        $columns[$this->translate('Existing Data Lists')] = $enum;
    }

    protected function enumImportSource()
    {
        $sources = $this->db->enumImportSource();
        $usedIds = $this->rule->listInvolvedSourceIds();
        if (empty($usedIds)) {
            return $this->optionalEnum($sources);
        }
        $usedSources = array();
        foreach ($usedIds as $id) {
            $usedSources[$id] = $sources[$id];
            unset($sources[$id]);
        }

        if (empty($sources)) {
            return $this->optionalEnum($usedSources);
        }

        return $this->optionalEnum(
            array(
                $this->translate('Used sources') => $usedSources,
                $this->translate('Other sources') => $sources
            )
        );
    }

    /**
     * @return array
     * @throws \Icinga\Exception\ConfigurationError
     * @throws \Icinga\Exception\NotFoundError
     */
    protected function listSourceColumns()
    {
        $columns = array();
        $source = $this->getImportSource();
        $hook = $this->getImportSourceHook();
        foreach ($hook->listColumns() as $col) {
            $columns['${' . $col . '}'] = $col;
        }

        foreach ($source->listModifierTargetProperties() as $property) {
            $columns['${' . $property . '}'] = $property;
        }

        return $columns;
    }

    protected function listDestinationFields()
    {
        $props = [];
        $special = [];
        $dummy = $this->dummyObject();

        if ($dummy instanceof IcingaObject) {
            if ($dummy->supportsCustomVars()) {
                $special['vars.*'] = $this->translate('Custom variable (vars.)');
                $special['vars']   = $this->translate('All custom variables (vars)');
            }
            if ($dummy->supportsImports()) {
                $special['import']  = $this->translate('Inheritance (import)');
            }
            if ($dummy->supportsArguments()) {
                $special['arguments']  = $this->translate('Arguments');
            }
            if ($dummy->supportsGroups()) {
                $special['groups']  = $this->translate('Group membership');
            }
            if ($dummy->supportsRanges()) {
                $special['ranges']  = $this->translate('Time ranges');
            }
        }

        foreach ($dummy->listProperties() as $prop) {
            if ($dummy instanceof IcingaObject && $prop === 'id') {
                continue;
            }

            // TODO: allow those fields, but munge them (store ids)
            //if (preg_match('~_id$~', $prop)) continue;
            if (substr($prop, -3) === '_id') {
                $short = substr($prop, 0, -3);
                if ($dummy instanceof IcingaObject) {
                    if ($dummy->hasRelation($short)) {
                        $prop = $short;
                    } else {
                        continue;
                    }
                }
            }

            $props[$prop] = $prop;
        }

        if ($dummy instanceof IcingaObject) {
            foreach ($dummy->listMultiRelations() as $prop) {
                $props[$prop] = sprintf('%s (%s)', $prop, $this->translate('a list'));
            }
        }

        ksort($props);

        $result = [];
        if (! empty($special)) {
            $result[$this->translate('Special properties')] = $special;
        }
        if (! empty($props)) {
            $result[$this->translate('Object properties')] = $props;
        }

        return $result;
    }

    /**
     * @return ImportSource
     * @throws \Icinga\Exception\NotFoundError
     */
    protected function getImportSource()
    {
        if ($this->importSource === null) {
            if ($this->hasObject()) {
                $id = (int) $this->object->get('source_id');
            } else {
                $id = (int) $this->getSentValue('source_id');
            }
            $this->importSource = ImportSource::loadWithAutoIncId($id, $this->db);
        }

        return $this->importSource;
    }

    /**
     * @return ImportSourceHook
     * @throws \Icinga\Exception\ConfigurationError
     * @throws \Icinga\Exception\NotFoundError
     */
    protected function getImportSourceHook()
    {
        if ($this->importSourceHook === null) {
            $this->importSourceHook = ImportSourceHook::loadByName(
                $this->getImportSource()->get('source_name'),
                $this->db
            );
        }

        return $this->importSourceHook;
    }

    public function onSuccess()
    {
        /** @var SyncProperty $object */
        $object = $this->getObject();
        $object->set('rule_id', $this->rule->get('id')); // ?!

        if ($this->getValue('use_filter') === 'n') {
            $object->set('filter_expression', null);
        }

        $sourceColumn = $this->getValue('source_column');
        $this->removeElement('source_column');

        if ($sourceColumn !== self::EXPRESSION) {
            $object->set('source_expression', $sourceColumn);
        }

        $destination = $this->getValue('destination_field');
        if ($destination === 'vars.*') {
            $destination = $this->getValue('customvar');
            $object->set('destination_field', 'vars.' . $destination);
        }

        return parent::onSuccess();
    }

    protected function dummyObject()
    {
        if ($this->dummyObject === null) {
            $this->dummyObject = IcingaObject::createByType(
                $this->rule->get('object_type'),
                array(),
                $this->db
            );
        }

        return $this->dummyObject;
    }

    public function setRule(SyncRule $rule)
    {
        $this->rule = $rule;
        return $this;
    }
}
