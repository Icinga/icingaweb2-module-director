<?php

namespace Icinga\Module\Director\Tables;

use Icinga\Module\Director\Restriction\ObjectRestriction;
use Icinga\Module\Director\Web\Table\QuickTable;

class IcingaHostTable extends QuickTable
{
    protected $objectRestrictions = array();

    protected $searchColumns = array(
        'host',
        'address',
        'display_name'
    );

    public function addObjectRestriction(ObjectRestriction $restriction)
    {
        $this->objectRestrictions[$restriction->getName()] = $restriction;
        return $this;
    }

    public function getColumns()
    {
        return array(
            'id'           => 'h.id',
            'host'         => 'h.object_name',
            'object_type'  => 'h.object_type',
            'address'      => 'h.address',
            'disabled'     => 'h.disabled',
            'display_name' => 'h.address',
        );
    }

    protected function getActionUrl($row)
    {
        return $this->url('director/host', array('name' => $row->host));
    }

    protected function getMultiselectProperties()
    {
        return array(
            'url'       => 'director/hosts/edit',
            'sourceUrl' => 'director/hosts',
            'keys'      => array('name'),
        );
    }

    protected function getRowClasses($row)
    {
        if ($row->disabled === 'y') {
            return 'disabled';
        } else {
            return null;
        }
    }

    public function getTitles()
    {
        $view = $this->view();
        return array(
            'host'    => $view->translate('Hostname'),
            'address' => $view->translate('Address'),
        );
    }

    protected function getUnfilteredQuery()
    {
        $query = $this->db()->select()->from(
            array('h' => 'icinga_host'),
            array()
        )->order('h.object_name');

        foreach ($this->objectRestrictions as $restriction) {
            $restriction->applyToHostsQuery($query);
        }

        return $query;
    }

    public function getBaseQuery()
    {
        return $this->getUnfilteredQuery();
    }
}
