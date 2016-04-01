<?php

namespace Icinga\Module\Director\Forms;

use Exception;
use Icinga\Exception\InvalidPropertyException;
use Icinga\Module\Director\Hook\ImportSourceHook;
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

    private $importSource;

    private $dummyObject;

    const EXPRESSION = '__EXPRESSION__';

    public function setup()
    {
        $this->addHtml(sprintf('<h3>%s</h3>', $this->getView()->escape($this->rule->rule_name)));
        $this->addHidden('rule_id', $this->rule_id);

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

        if ($destination === 'import') {
            $funcTemplates = 'enum' . ucfirst($this->rule->object_type) . 'Templates';
            $templates = $this->db->$funcTemplates();
            if (! empty($templates)) {
                $templates = array_combine($templates, $templates);
            }

            $this->addElement('select', 'source_expression', array(
                'label'        => $this->translate('Template'), // Objecttype?
                'multiOptions' => $this->optionalEnum($templates),
                'required'     => true,
                'class'        => 'autosubmit',
            ));
        } else {
            $this->addSourceColumnElement();
        }

        if ($this->hasObject()) {
            if (($col = $this->getObject()->getSourceColumn()) === null) {
                $this->setElementValue('source_column', self::EXPRESSION);
                $this->addElement('text', 'source_expression', array(
                    'label'    => $this->translate('Source Expression'),
                    'required' => true,
                ));
                if ($this->getSentValue('source_column') === '${' . self::EXPRESSION . '}') {
                    unset($this->source_column);
                }
            } else {
                $this->setElementValue('source_column', $col);
            }
        }

        if ($this->getSentValue('source_column') === self::EXPRESSION) {
            $this->addElement('text', 'source_expression', array(
                'label'    => $this->translate('Source Expression'),
                'required' => true,
            ));
            if ($this->getSentValue('source_column') === '${' . self::EXPRESSION . '}') {
                unset($this->source_column);
            }
        }

        /*
        if ($this->hasObject()) {
            // TODO: Add up/down links to table
            $this->addElement('text', 'priority', array(
                'label'       => $this->translate('Priority'),
                'description' => $this->translate('Priority for the specified source expression'),
                'required'    => true,
            ));
        }
        */


        // TODO: we need modifier
        $this->addElement('select', 'use_filter', array(
            'label'        => $this->translate('Set based on filter'),
            'ignore'       => true,
            'class'        => 'autosubmit',
            'required'     => true,
            'multiOptions' => $this->enumBoolean()
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
                    'Whether you want to merge or replace the destination field. Makes no difference for strings'
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

    protected function addSourceColumnElement()
    {
        $error = false;
        try {
            $data = $this->listSourceColumns();
        } catch (Exception $e) {
            $data = array();
            $error = sprintf(
                $this->translate('Unable to fetch data: %s'),
                $e->getMessage()
            );
        }

        $this->addElement('select', 'source_column', array(
            'label'        => $this->translate('Source Column'),
            // TODO: List them as ${} ?
            'multiOptions' => $this->optionalEnum($data),
            'required'     => true,
            'class'        => 'autosubmit',
        ));

        if ($error) {
            $this->getElement('source_column')->addError($error);
        }

        return $this;
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

    protected function listSourceColumns()
    {
        $columns = $this->getImportSource()->listColumns();
        $columns = array_combine($columns, $columns);
        $columns[self::EXPRESSION] = $this->translate('Custom expression');
        return $columns;
    }

    protected function listDestinationFields()
    {
        $props = array();
        $special = array();
        $dummy = $this->dummyObject();

        if ($dummy instanceof IcingaObject) {
            if ($dummy->supportsCustomvars()) {
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
        }

        foreach ($dummy->listProperties() as $prop) {
            if ($prop === 'id') {
                continue;
            }

            // TODO: allow those fields, but munge them (store ids)
            //if (preg_match('~_id$~', $prop)) continue;
            if (substr($prop, -3) === '_id') {
                $prop = substr($prop, 0, -3);
                if (! $dummy instanceof IcingaObject || ! $dummy->hasRelation($prop)) {
                    continue;
                }
            }

            $props[$prop] = $prop;
        }

        ksort($props);

        return array(
            $this->translate('Special properties') => $special,
            $this->translate('Object properties') => $props
        );
    }

    protected function getImportSource()
    {
        if ($this->importSource === null) {
            if ($this->hasObject()) {
                $src = ImportSource::load($this->object->source_id, $this->db);
            } else {
                $src = ImportSource::load($this->getSentValue('source_id'), $this->db);
            }
            $this->importSource = ImportSourceHook::loadByName($src->source_name, $this->db);
        }

        return $this->importSource;
    }

    public function onSuccess()
    {
        $sourceColumn = $this->getValue('source_column');
        if ($sourceColumn === self::EXPRESSION) {
            unset($this->source_column);
            $this->removeElement('source_column');
        } else {
            if (! $this->getElement('source_expression')) {
                $this->addHidden('source_expression', '${' . $sourceColumn . '}');
            }
        }

        $object = $this->getObject();
        $object->rule_id = $this->rule->id; // ?!

        if ($this->getValue('use_filter') === 'n') {
            $object->filter_expression = null;
        }

        $destination = $this->getValue('destination_field');
        if ($destination === 'vars.*') {
            $destination = $this->getValue('customvar');
            $object->destination_field = 'vars.' . $destination;
        }

        if ($object->hasBeenModified()) {
            if (! $object->hasBeenLoadedFromDb()) {
                $object->priority = $this->rule->getPriorityForNextProperty();
            }
        }


        return parent::onSuccess();
    }

    protected function dummyObject()
    {
        if ($this->dummyObject === null) {
            $this->dummyObject = IcingaObject::createByType(
                $this->rule->object_type,
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
