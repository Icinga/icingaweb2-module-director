<?php

namespace Icinga\Module\Director\Controllers;

use Icinga\Module\Director\Web\Controller\ActionController;

class IndexController extends ActionController
{
    protected $globalTypes = array(
        'ApiUser',
        'Zone',
        'Endpoint',
        'TimePeriod',
    );

    public function indexAction()
    {
        $this->getTabs()->add('overview', array(
            'url' => $this->getRequest()->getUrl(),
            'label' => $this->translate('Overview')
        ))->activate('overview');

        if ($this->Config()->get('db', 'resource')) {
            $this->addGlobalTypeTabs();
        } else {
            $this->view->errorMessage = sprintf(
                $this->translate('No database resource has been configured yet. Please %s to complete your config'),
                $this->view->qlink($this->translate('click here'), 'director/settings')
            );
        }
    }

    protected function addGlobalTypeTabs()
    {
        $tabs = $this->getTabs();

        foreach ($this->globalTypes as $tabType) {
            $ltabType = strtolower($tabType);
            $tabs->add($ltabType, array(
                'label' => $this->translate(ucfirst($ltabType) . 's'),
                'url'   => sprintf('director/%ss', $ltabType)
            ));
        }
    }
}
