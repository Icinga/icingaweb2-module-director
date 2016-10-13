<?php

namespace Icinga\Module\Director\Controllers;

use Icinga\Module\Director\ConfigDiff;
use Icinga\Module\Director\IcingaConfig\IcingaConfig;
use Icinga\Module\Director\Web\Controller\ActionController;
use Icinga\Module\Director\Util;
use Icinga\Module\Director\Objects\IcingaObject;
use Exception;

class ShowController extends ActionController
{
    protected $defaultTab;

    protected $oldObject;

    protected function objectKey($entry)
    {
        if ($entry->object_type === 'icinga_service') {
            // TODO: this is not correct. Activity needs to get (multi) key support
            return array('name' => $entry->object_name);
        }

        return $entry->object_name;
    }

    protected function activityTabs($entry)
    {
        $db = $this->db();

        if (IcingaObject::existsByType($entry->object_type, $this->objectKey($entry), $db)) {
            $this->view->currentObject = IcingaObject::loadByType(
                $entry->object_type,
                $entry->object_name,
                $db
            );
        }

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
                $this->defaultTab = 'new';
            }
        }

        if (in_array($entry->action_name, array('delete', 'modify'))) {
            $tabs->add('old', array(
                'label'     => $this->translate('Former object'),
                'url'       => 'director/show/activitylog',
                'urlParams' => array('id' => $entry->id, 'show' => 'old')
            ));

            if ($this->defaultTab === null) {
                $this->defaultTab = 'old';
            }
        }

        return $tabs;
    }

    protected function newConfig($entry)
    {
        return $this->newObject($entry)->toSingleIcingaConfig();
    }

    protected function oldConfig($entry)
    {
        return $this->oldObject($entry)->toSingleIcingaConfig();
    }

    protected function showDiff($entry)
    {
        $this->view->title = sprintf('%s config diff', $entry->object_name);
        $this->getTabs()->activate('diff');

        $oldConfig = $this->oldConfig($entry);
        $newConfig = $this->newConfig($entry);

        $oldFilenames = $oldConfig->getFileNames();
        $newFilenames = $newConfig->getFileNames();

        $fileNames = array_merge($oldFilenames, $newFilenames);

        $this->view->diffs = array();
        foreach ($fileNames as $filename) {
            if (in_array($filename, $oldFilenames)) {
                $left = $oldConfig->getFile($filename)->getContent();
            } else {
                $left = '';
            }

            if (in_array($filename, $newFilenames)) {
                $right = $newConfig->getFile($filename)->getContent();
            } else {
                $right = '';
            }
            if ($left === $right) {
                continue;
            }

            $d = ConfigDiff::create($left, $right);

            $this->view->diffs[$filename] = $d->renderHtml();
        }
    }

    protected function showOld($entry)
    {
        $this->view->title = sprintf('%s former config', $entry->object_name);
        $this->getTabs()->activate('old');
        $this->showConfig($this->oldConfig($entry));
    }

    protected function showNew($entry)
    {
        $this->view->title = sprintf('%s new config', $entry->object_name);
        $this->getTabs()->activate('new');
        $this->showConfig($this->newConfig($entry));
    }

    protected function oldObject($entry)
    {
        if ($this->oldObject === null) {
            $this->oldObject = $this->createObject(
                $entry->object_type,
                $entry->old_properties
            );
        }

        return $this->oldObject;
    }

    protected function newObject($entry)
    {
        return $this->createObject(
            $entry->object_type,
            $entry->new_properties
        );
    }

    protected function showConfig(IcingaConfig $config)
    {
        $this->view->diffs = array();
        foreach ($config->getFileNames() as $filename) {
            $this->view->diffs[$filename] = sprintf(
                '<pre>%s</pre>',
                $this->view->escape($config->getFile($filename)->getContent())
            );
        }
        return $this;
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
        $v = $this->view;

        $v->object_type = $this->params->get('type');
        $v->object_name = $this->params->get('name');

        if ($id = $this->params->get('id')) {
            $v->entry = $this->db()->fetchActivityLogEntryById($id);
        } elseif ($checksum = $this->params->get('checksum')) {
            $v->entry = $this->db()->fetchActivityLogEntry($checksum);
            $id = $v->entry->id;
        }

        $v->neighbors = $this->db()->getActivitylogNeighbors(
            $id,
            $v->object_type,
            $v->object_name
        );

        $entry = $v->entry;

        if ($entry->old_properties) {
            $this->view->form = $this
                ->loadForm('restoreObject')
                ->setDb($this->db())
                ->setObject($this->oldObject($entry))
                ->handleRequest();
        }

        $this->activityTabs($entry);
        $this->showInfo($entry);
        $func = 'show' . ucfirst($this->params->get('show', $this->defaultTab));
        $this->$func($entry);
    }

    protected function createObject($type, $props)
    {
        $props = json_decode($props);
        $newProps = array(
            'object_name' => $props->object_name
        );
        if (property_exists($props, 'object_type')) {
            $newProps['object_type'] = $props->object_type;
        }

        return IcingaObject::createByType(
            $type,
            $newProps,
            $this->db()
        )->setProperties((array) $props);
    }
}
