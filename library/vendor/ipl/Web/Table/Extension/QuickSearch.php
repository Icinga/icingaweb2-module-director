<?php

namespace ipl\Web\Table\Extension;

use ipl\Html\BaseElement;
use ipl\Html\Html;
use ipl\Web\Url;
use ipl\Web\Widget\Controls;

trait QuickSearch
{
    /** @var BaseElement */
    private $quickSearchForm;

    public function getQuickSearch(BaseElement $parent, Url $url)
    {
        $this->requireQuickSearchForm($parent, $url);
        $search = $url->getParam('q');
        return $search;
    }

    private function requireQuickSearchForm(BaseElement $parent, Url $url)
    {
        if ($this->quickSearchForm === null) {
            $this->quickSearchForm = $this->buildQuickSearchForm($parent, $url);
        }
    }

    private function buildQuickSearchForm(BaseElement $parent, Url $url)
    {
        $search = $url->getParam('q');

        $form = Html::tag('form', [
            'action' => $url->without(array('q', 'page', 'modifyFilter'))->getAbsoluteUrl(),
            'class'  => ['quicksearch'],
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

        $this->addQuickSearchToControls($parent, $form);

        return $form;
    }

    protected function addQuickSearchToControls(Controls $parent, BaseElement $form)
    {
        $title = $parent->getTitleElement();
        if ($title === null) {
            $parent->prepend($form);
        } else {
            $input = $form->getFirst('input');
            $form->remove($input);
            $title->add($input);
            $form->add($title);
            $parent->setTitleElement($form);
        }

        return $this;
    }
}
