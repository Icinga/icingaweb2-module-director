<?php

namespace Icinga\Module\Director\Controllers;

use Icinga\Module\Director\Objects\IcingaHost;
use Icinga\Module\Director\Objects\IcingaService;
use Icinga\Module\Director\Web\Controller\ActionController;
use Icinga\Data\Filter\Filter;

class SuggestController extends ActionController
{
    /*
    // TODO: Allow any once applying restrictions here
    protected function checkDirectorPermissions()
    {
    }
    */

    public function indexAction()
    {
        // TODO: Using some temporarily hardcoded methods, should use DataViews later on
        $context = $this->getRequest()->getPost('context');
        $func = 'suggest' . ucfirst($context);
        if (method_exists($this, $func)) {
            $all = $this->$func();
        } else {
            $all = array();
        }
        // TODO: also get cursor position and eventually add an asterisk in the middle
        // tODO: filter also when fetching, eventually limit somehow
        $search = $this->getRequest()->getPost('value');
        $begins = array();
        $matches = array();
        $begin = Filter::expression('value', '=', $search . '*');
        $middle = Filter::expression('value', '=', '*' . $search . '*')->setCaseSensitive(false);
        $prefixes = array();
        foreach ($all as $str) {
            if (false !== ($pos = strrpos($str, '.'))) {
                $prefix = substr($str, 0, $pos) . '.';
                $prefixes[$prefix] = $prefix;
            }
            if (strlen($search)) {
                $row = (object) array('value' => $str);
                if ($begin->matches($row)) {
                    $begins[] = $this->highlight($str, $search);
                } elseif ($middle->matches($row)) {
                    $matches[] = $this->highlight($str, $search);
                }
            } else {
                $matches[] = $str;
            }
        }

        $containing = array_slice(array_merge($begins, $matches), 0, 30);
        $suggestions = $containing;

        if ($func === 'suggestHostFilterColumns' || $func === 'suggestHostaddresses') {
            ksort($prefixes);

            if (count($suggestions) < 5) {
                $suggestions = array_merge($suggestions, array_keys($prefixes));
            }
        }
        $this->view->suggestions = $suggestions;
    }

    /**
     * One more dummy helper for tests
     *
     * TODO: Should not remain here
     *
     * @return array
     */
    protected function suggestLocations()
    {
        $db = $this->db()->getDbAdapter();
        $query = $db->select()
            ->distinct()
            ->from('icinga_host_var', 'varvalue')
            ->where('varname = ?', 'location')
            ->order('varvalue');
        return $db->fetchCol($query);
    }

    protected function suggestHostnames()
    {
        $db = $this->db()->getDbAdapter();
        $query = $db->select()
            ->from('icinga_host', 'object_name')
            ->order('object_name')
            ->where("object_type = 'object'");
        return $db->fetchCol($query);
    }

    protected function suggestHosttemplates()
    {
        $db = $this->db()->getDbAdapter();
        $query = $db->select()
            ->from('icinga_host', 'object_name')
            ->order('object_name')
            ->where("object_type = 'template'")
            ->where('template_choice_id IS NULL');
        return $db->fetchCol($query);
    }

    protected function suggestServicetemplates()
    {
        $db = $this->db()->getDbAdapter();
        $query = $db->select()
            ->from('icinga_service', 'object_name')
            ->order('object_name')
            ->where("object_type = 'template'");
        return $db->fetchCol($query);
    }

    protected function suggestCommandtemplates()
    {
        $db = $this->db()->getDbAdapter();
        $query = $db->select()
            ->from('icinga_command', 'object_name')
            ->order('object_name')
            ->where("object_type = 'template'");
        return $db->fetchCol($query);
    }

    protected function suggestCheckcommandnames()
    {
        $db = $this->db()->getDbAdapter();
        $query = $db->select()->from('icinga_command', 'object_name')->order('object_name');
        return $db->fetchCol($query);
    }

    protected function suggestHostgroupnames()
    {
        $db = $this->db()->getDbAdapter();
        $query = $db->select()->from('icinga_hostgroup', 'object_name')->order('object_name');
        return $db->fetchCol($query);
    }

    protected function suggestHostaddresses()
    {
        $db = $this->db()->getDbAdapter();
        $query = $db->select()->from('icinga_host', 'address')->order('address');
        return $db->fetchCol($query);
    }

    protected function suggestHostFilterColumns()
    {
        $all = IcingaHost::enumProperties($this->db(), 'host.');
        $all = array_merge(
            array_keys($all[$this->translate('Host properties')]),
            array_keys($all[$this->translate('Custom variables')])
        );
        natsort($all);
        return $all;
    }

    protected function suggestServiceFilterColumns()
    {
        $all = IcingaService::enumProperties($this->db(), 'service.');
        $all = array_merge(
            array_keys($all[$this->translate('Service properties')]),
            array_keys($all[$this->translate('Host properties')]),
            array_keys($all[$this->translate('Host Custom variables')]),
            array_keys($all[$this->translate('Custom variables')])
        );
        // natsort($all);
        return $all;
    }

    protected function highlight($val, $search)
    {
        $search = $this->view->escape($search);
        $val = $this->view->escape($val);
        return preg_replace(
            '/(' . preg_quote($search, '/') . ')/i',
            '<strong>\1</strong>',
            $val
        );
    }
}
