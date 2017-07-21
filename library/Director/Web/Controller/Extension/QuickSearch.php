<?php

namespace Icinga\Module\Director\Web\Controller\Extension;

use ipl\Html\Html;

trait QuickSearch
{
    public function quickSearch()
    {
        $search = $this->params->get('q');

        $form = Html::tag('form', [
            'action' => $this->getRequest()->getUrl()->without(array('q', 'page', 'modifyFilter')),
            'class'  => ['quicksearch', 'inline'],
            'method' => 'GET'
        ]);

        $form->add(
            Html::tag('input', [
                'type' => 'text',
                'name' => 'q',
                'value' => $search,
                'placeholder' => $this->translate('Search...'),
                'class' => 'search'
            ])
        );

        $this->controls()->add($form);

        return $search;
    }
}
