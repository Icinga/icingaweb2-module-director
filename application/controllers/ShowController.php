<?php

namespace Icinga\Module\Director\Controllers;

use Icinga\Module\Director\IcingaConfig\IcingaConfig;
use Icinga\Module\Director\Objects\IcingaObject;
use Icinga\Module\Director\ConfigDiff;
use Icinga\Module\Director\Util;
use Icinga\Module\Director\Web\Controller\ActionController;

class ShowController extends ActionController
{
    public function activitylogAction()
    {
        if ($id = $this->params->get('id')) {
            $this->view->entry = $this->db()->fetchActivityLogEntryById($id);
        } elseif ($checksum = $this->params->get('checksum')) {
            $this->view->entry = $this->db()->fetchActivityLogEntry(Util::hex2binary($checksum));
        }

        $entry = $this->view->entry;

        if ($entry->old_properties) {
            $old = $this->createObject($entry->object_type, $entry->old_properties);
            if ($entry->new_properties) {
                $new = $this->createObject($entry->object_type, $entry->old_properties);
                $new->setProperties((array) json_decode($entry->new_properties));
            } else {
                $new = null;
            }
        } else {
            $old = null;
            $new = $this->createObject($entry->object_type, $entry->new_properties);
        }

        if ($old && $new) {
            $d = ConfigDiff::create($old, $new);
            $this->view->diff = strtr(
                $d->renderHtml(),
                array('\\n' => "\\n\n")
            );
        }

        $this->view->entry = $entry;
        $this->view->newObject = $new;
        $this->view->oldObject = $old;
        $this->view->title = $this->translate('Activity');
    }

    protected function createObject($type, $props)
    {
        $props = json_decode($props);
        return IcingaObject::createByType($type, (array) $props, $this->db());
    }
}
