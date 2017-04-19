<?php

namespace Icinga\Module\Director\Web\Controller;

use ipl\Html\Container;
use ipl\Html\Html;
use ipl\Html\HtmlTag;
use ipl\Translation\TranslationHelper;
use ipl\Web\Component\ActionBar;
use ipl\Web\Component\Tabs;

abstract class SimpleController extends ActionController
{
    private $tabs;

    private $actions;

    public function init()
    {
        parent::init();
        $this->setViewScript('simple');
    }

    /**
     * @param $title
     * @return $this
     */
    public function setTitle($title)
    {
        $args = func_get_args();
        array_shift($args);
        if (! empty($args)) {
            $title = vsprintf($title, $args);
        }

        $this->view->title = $title;

        return $this;
    }

    /**
     * @return ActionBar
     */
    public function actions()
    {
        if ($this->actions === null) {
            $this->actions = new ActionBar();
            $this->controls()->add($this->actions());
        }

        return $this->actions;
    }

    public function quickSearch()
    {
        $search = $this->params->shift('q');

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
           ]
        ));

        $this->controls()->add($form);

        return $search;
    }

    /**
     * @param $title
     * @return $this
     */
    public function addTitle($title)
    {
        $args = func_get_args();
        call_user_func_array([$this, 'setTitle'], $args);
        $this->controls()->add(
            HtmlTag::h1($this->view->title)
        );

        return $this;
    }

    public function addSingleTab($title, $url = null, $name = 'main')
    {
        if ($url === null) {
            $url = $this->getRequest()->getUrl();
        }

        $this->tabs()->add($name, [
            'label' => $title,
            'url'   => $url,
        ])->activate($name);

        return $this;
    }

    public function tabs()
    {
        if ($this->tabs === null) {
            $this->tabs = new Tabs();
            $this->controls()->prepend($this->tabs);
        }

        return $this->tabs;
    }

    /**
     * @return Container
     */
    public function controls()
    {
        if ($this->view->controls === null) {
            $this->view->controls = Container::create([
                'class' => 'controls'
            ]);
        }

        return $this->view->controls;
    }

    /**
     * @return Container
     */
    public function content()
    {
        if ($this->view->content === null) {
            $this->view->content = Container::create([
                'class' => 'content'
            ]);
        }

        return $this->view->content;
    }
}
