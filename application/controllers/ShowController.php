<?php

namespace Icinga\Module\Director\Controllers;

use Icinga\Module\Director\ConfigDiff;
use Icinga\Module\Director\Web\Controller\ActionController;
use Icinga\Module\Director\Util;
use Icinga\Module\Director\Objects\IcingaObject;

class ShowController extends ActionController
{
    protected $defaultTab;

    protected function activityTabs($entry)
    {
        $tabs = $this->getTabs();
        if ($entry->action_name === 'modify') {
            $tabs->add('diff', array(
                'label'     => $this->translate('Diff'),
                'url'       => 'director/show/activitylog',
                'urlParams' => array('id' => $entry->id)
            ));

            $this->defaultTab = 'diff';
        }

        if (in_array($entry->action_name, array('create', 'modify'))) {
            $tabs->add('new', array(
                'label'     => $this->translate('New object'),
                'url'       => 'director/show/activitylog',
                'urlParams' => array('id' => $entry->id, 'show' => 'new')
            ));

            if ($this->defaultTab === null) {
                $this->defaultTab = 'diff';
            }
        }

        if (in_array($entry->action_name, array('create', 'modify'))) {
            $tabs->add('old', array(
                'label'     => $this->translate('Former object'),
                'url'       => 'director/show/activitylog',
                'urlParams' => array('id' => $entry->id, 'show' => 'old')
            ));

            if ($this->defaultTab === null) {
                $this->defaultTab = 'diff';
            }
        }

        return $tabs;
    }

    protected function showDiff($entry)
    {
        $this->view->title = sprintf('%s config diff', $entry->object_name);
        $this->getTabs()->activate('diff');
        $d = ConfigDiff::create(
            $this->oldObject($entry),
            $this->newObject($entry)
        );

        $this->view->output = $d->renderHtml();
    }

    protected function showOld($entry)
    {
        $this->view->title = sprintf('%s former config', $entry->object_name);
        $this->getTabs()->activate('old');
        $this->showObject($this->oldObject($entry));
    }

    protected function showNew($entry)
    {
        $this->view->title = sprintf('%s new config', $entry->object_name);
        $this->getTabs()->activate('new');
        $this->showObject($this->newObject($entry));
    }

    protected function oldObject($entry)
    {
        return $this->createObject($entry->object_type, $entry->old_properties);
    }

    protected function newObject($entry)
    {
        return $this->createObject(
            $entry->object_type,
            $entry->old_properties
        )->setProperties((array) json_decode($entry->new_properties));
    }

    protected function showObject($object)
    {
        $this->view->output = '<pre>' . $this->view->escape(
            (string) $object
        ) . '</pre>';
    }

    protected function showInfo($entry)
    {
        $typeName = $this->translate(
            ucfirst(preg_replace('/^icinga_/', '', $entry->object_type)) // really?
        );

        switch ($entry->action_name) {
            case 'create':
                $this->view->title = sprintf(
                    $this->translate('%s "%s" has been created'),
                    $typeName,
                    $entry->object_name
                );
                break;
            case 'delete':
                $this->view->title = sprintf(
                    $this->translate('%s "%s" has been deleted'),
                    $typeName,
                    $entry->object_name
                );
                break;
            case 'modify':
                $this->view->title = sprintf(
                    $this->translate('%s "%s" has been modified'),
                    $typeName,
                    $entry->object_name
                );
                break;
        }
    }

    public function activitylogAction()
    {
        if ($id = $this->params->get('id')) {
            $this->view->entry = $this->db()->fetchActivityLogEntryById($id);
        } elseif ($checksum = $this->params->get('checksum')) {
            $this->view->entry = $this->db()->fetchActivityLogEntry(Util::hex2binary($checksum));
        }

        $entry = $this->view->entry;
        $this->activityTabs($entry);
        $this->showInfo($entry);
        $func = 'show' . ucfirst($this->params->get('show', $this->defaultTab));
        $this->$func($entry);
    }

    protected function createObject($type, $props)
    {
        $props = json_decode($props);
        return IcingaObject::createByType($type, (array) $props, $this->db());
    }
}
