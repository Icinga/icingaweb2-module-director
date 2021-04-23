<?php

namespace Icinga\Module\Director\Forms;

use Icinga\Module\Director\Web\Form\DirectorObjectForm;

class IcingaScheduledDowntimeForm extends DirectorObjectForm
{
    /**
     * @throws \Zend_Form_Exception
     */
    public function setup()
    {
        if ($this->isTemplate()) {
            $this->addElement('text', 'object_name', [
                'label'    => $this->translate('Template name'),
                'required' => true,
            ]);
        } else {
            $this->addElement('text', 'object_name', [
                'label'    => $this->translate('Downtime name'),
                'required' => true,
            ]);
        }

        if ($this->object()->isApplyRule()) {
            $this->eventuallyAddNameRestriction('director/scheduled-downtime/apply/filter-by-name');
        }
        $this->addImportsElement();
        $this->addElement('text', 'author', [
            'label'       => $this->translate('Author'),
            'description' => $this->translate(
                'This name will show up as the author for ever related downtime'
                . ' comment'
            ),
            'required'    => ! $this->isTemplate()
        ]);
        $this->addElement('textarea', 'comment', [
            'label'    => $this->translate('Comment'),
            'description' => $this->translate(
                'Every related downtime will show this comment'
            ),
            'required' => ! $this->isTemplate(),
            'rows'     => 4,
        ]);
        $this->addBoolean('fixed', [
            'label'       => $this->translate('Fixed'),
            'description' => $this->translate(
                'Whether this downtime is fixed or flexible. If unsure please'
                . ' check the related documentation:'
                . ' https://icinga.com/docs/icinga2/latest/doc/08-advanced-topics/#downtimes'
            ),
            'required'    => ! $this->isTemplate(),
        ]);
        $this->addElement('text', 'duration', [
            'label'       => $this->translate('Duration'),
            'description' => $this->translate(
                'How long the downtime lasts. Only has an effect for flexible'
                . ' (non-fixed) downtimes. Time in seconds, supported suffixes'
                . ' include ms (milliseconds), s (seconds), m (minutes),'
                . ' h (hours) and d (days). To express "90 minutes" you might'
                . ' want to write 1h 30m'
            )
        ]);
        $this->addDisabledElement();
        $this->addAssignmentElements();
        $this->setButtons();
    }


    /**
     * @return $this
     * @throws \Zend_Form_Exception
     */
    protected function addAssignmentElements()
    {
        if ($this->isTemplate()) {
            return $this;
        }

        $this->addElement('select', 'apply_to', [
            'label'        => $this->translate('Apply to'),
            'description'  => $this->translate(
                'Whether this dependency should affect hosts or services'
            ),
            'required'     => true,
            'class'        => 'autosubmit',
            'multiOptions' => $this->optionalEnum([
                'host'    => $this->translate('Hosts'),
                'service' => $this->translate('Services'),
            ])
        ]);

        $applyTo = $this->getSentOrObjectValue('apply_to');

        if (! $applyTo) {
            return $this;
        }

        if ($applyTo === 'host') {
            $this->addBoolean('with_services', [
                'label'       => $this->translate('With Services'),
                'description' => $this->translate(
                    'Whether Downtimes should also explicitly be scheduled for'
                    . ' all Services belonging to affected Hosts'
                )
            ]);
        }

        $suggestionContext = ucfirst($applyTo) . 'FilterColumns';
        $this->addAssignFilter([
            'suggestionContext' => $suggestionContext,
            'required' => true,
            'description' => $this->translate(
                'This allows you to configure an assignment filter. Please feel'
                . ' free to combine as many nested operators as you want. The'
                . ' "contains" operator is valid for arrays only. Please use'
                . ' wildcards and the = (equals) operator when searching for'
                . ' partial string matches, like in *.example.com'
            )
        ]);

        return $this;
    }

    protected function setObjectSuccessUrl()
    {
        $this->setSuccessUrl(
            'director/scheduled-downtime',
            $this->object()->getUrlParams()
        );
    }
}
