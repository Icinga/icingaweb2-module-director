<?php

namespace Icinga\Module\Director\Forms;

use Icinga\Module\Director\Web\Form\DirectorObjectForm;

class IcingaTimePeriodForm extends DirectorObjectForm
{
    /**
     * @throws \Zend_Form_Exception
     */
    public function setup()
    {
        $this->addElement('text', 'object_name', [
            'label'    => $this->translate('Name'),
            'required' => true,
        ]);

        $this->addElement('text', 'display_name', [
            'label' => $this->translate('Display Name'),
        ]);

        if ($this->isTemplate()) {
            $this->addElement('text', 'update_method', [
                'label' => $this->translate('Update Method'),
                'value' => 'LegacyTimePeriod',
            ]);
        } else {
            // TODO: I'd like to skip this for objects inheriting from a template
            //       with a defined update_method. However, unfortunately it's too
            //       early for $this->object()->getResolvedProperty('update_method').
            //       Should be fixed.
            $this->addHidden('update_method', 'LegacyTimePeriod');
        }

        $this->addIncludeExclude()
            ->addImportsElement()
            ->setButtons();
    }

    /**
     * @return $this
     * @throws \Zend_Form_Exception
     */
    protected function addIncludeExclude()
    {
        $periods = [];
        foreach ($this->db->enumTimeperiods() as $id => $period) {
            if ($this->object === null || $this->object->get('object_name') !== $period) {
                $periods[$period] = $period;
            }
        }

        if (empty($periods)) {
            return $this;
        }

        $this->addElement('extensibleSet', 'includes', [
            'label'        => $this->translate('Include period'),
            'multiOptions' => $this->optionalEnum($periods),
            'description'  => $this->translate(
                'Include other time periods into this.'
            ),
        ]);

        $this->addElement('extensibleSet', 'excludes', [
            'label'        => $this->translate('Exclude period'),
            'multiOptions' => $this->optionalEnum($periods),
            'description'  => $this->translate(
                'Exclude other time periods from this.'
            ),
        ]);

        $this->optionalBoolean(
            'prefer_includes',
            $this->translate('Prefer includes'),
            $this->translate('Whether to prefer timeperiods includes or excludes. Default to true.')
        );

        return $this;
    }
}
