<?php

namespace Icinga\Module\Director\Controllers;

use Icinga\Module\Director\Web\Controller\ObjectController;

class TimeperiodController extends ObjectController
{
    public function init()
    {
        parent::init();
        if ($this->object && $this->object->hasBeenLoadedFromDb()) {
            $this->getTabs()->add('ranges', array(
                'url'       => 'director/timeperiod/ranges',
                'urlParams' => $this->object->getUrlParams(),
                'label'     => $this->translate('Ranges')
            ));
        }
    }

    public function rangesAction()
    {
        $this->getTabs()->activate('ranges');
        $this->view->form = $form = $this->loadForm('icingaTimePeriodRange');
        $form
            ->setTimePeriod($this->object)
            ->setDb($this->db());
        if ($name = $this->params->get('range')) {
            $this->view->actionLinks = $this->view->qlink(
                $this->translate('back'),
                $this->getRequest()->getUrl()->without('range_id'),
                null,
                array('class' => 'icon-left-big')
            );
            $form->loadObject(array(
                'timeperiod_id'  => $this->object->id,
                'timeperiod_key' => $name,
                'range_type'     => $this->params->get('range_type')
            ));
        }
        $form->handleRequest();

        $this->view->table = $this->loadTable('icingaTimePeriodRange')
            ->setTimePeriod($this->object);
        $this->view->title = $this->translate('Time period ranges');
        $this->render('object/fields', null, true); // TODO: render table
    }
}
