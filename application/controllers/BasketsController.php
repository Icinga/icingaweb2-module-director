<?php

namespace Icinga\Module\Director\Controllers;

use ipl\Html\Html;
use gipfl\IcingaWeb2\Link;
use Icinga\Module\Director\Web\Controller\ActionController;
use Icinga\Module\Director\Web\Table\BasketTable;

class BasketsController extends ActionController
{
    protected $isApified = false;

    public function indexAction()
    {
        $this->setAutorefreshInterval(10);
        $this->addSingleTab($this->translate('Baskets'));
        $this->actions()->add([
            Link::create(
                $this->translate('Create'),
                'director/basket/create',
                null,
                ['class' => 'icon-plus']
            ),
            Link::create(
                $this->translate('Upload'),
                'director/basket/upload',
                null,
                ['class' => 'icon-upload']
            ),
        ]);
        $this->addTitle($this->translate('Configuration Baskets'));
        $this->content()->add(Html::tag('p', $this->translate(
            'A Configuration Basket references specific Configuration'
            . ' Objects or all objects of a specific type. It has been'
            . ' designed to share Templates, Import/Sync strategies and'
            . ' other base Configuration Objects. It is not a tool to'
            . ' operate with single Hosts or Services.'
        )));
        $this->content()->add(Html::tag('p', $this->translate(
            'You can create Basket snapshots at any time, this will persist'
            . ' a serialized representation of all involved objects at that'
            . ' moment in time. Snapshots can be exported, imported, shared'
            . ' and restored - to the very same or another Director instance.'
        )));
        $table = (new BasketTable($this->db()))
            ->setAttribute('data-base-target', '_self');
        // TODO: temporarily disabled, this was a thing in dipl
        if (/*$table->hasSearch() || */count($table)) {
            $table->renderTo($this);
        }
    }
}
