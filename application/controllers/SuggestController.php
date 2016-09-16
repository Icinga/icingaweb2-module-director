<?php

namespace Icinga\Module\Director\Controllers;

use Icinga\Module\Director\Objects\IcingaService;
use Icinga\Module\Director\Web\Controller\ActionController;
use Icinga\Data\Filter\Filter;
use ipl\Html\Util;
use Icinga\Module\Director\Objects\HostApplyMatches;

class SuggestController extends ActionController
{
    protected function checkDirectorPermissions()
    {
    }

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
                $matches[] = Util::escapeForHtml($str);
            }
        }

        $containing = array_slice(array_merge($begins, $matches), 0, 100);
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
        $this->assertPermission('director/hosts');
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
        $this->assertPermission('director/hosts');
        $db = $this->db()->getDbAdapter();
        $query = $db->select()
            ->from('icinga_host', 'object_name')
            ->order('object_name')
            ->where("object_type = 'object'");
        return $db->fetchCol($query);
    }

    protected function suggestServicenames()
    {
        $r=array();
        $this->assertPermission('director/services');
        $db = $this->db()->getDbAdapter();
        $for_host = $this->getRequest()->getPost('for_host');
        if (!empty($for_host)) {
            $tmp_host = IcingaHost::load($for_host,$this->db());
        }

        $query = $db->select()->distinct()
            ->from('icinga_service', 'object_name')
            ->order('object_name')
            ->where("object_type IN ('object','apply')");
        if (!empty($tmp_host)) {
            $query->where('host_id = ?',$tmp_host->id);
        }
        $r = array_merge($r,$db->fetchCol($query));
        if (!empty($tmp_host)) {
            $resolver = $tmp_host->templateResolver();
            foreach ($resolver->fetchResolvedParents() as $template_obj) {
                $query = $db->select()->distinct()
                    ->from('icinga_service', 'object_name')
                    ->order('object_name')
                    ->where("object_type IN ('object','apply')")
                    ->where('host_id = ?', $template_obj->id);
                $r = array_merge($r,$db->fetchCol($query));
            }

            $matcher = HostApplyMatches::prepare($tmp_host);
            foreach ($this->getAllApplyRules() as $rule) {
                if ($matcher->matchesFilter($rule->filter)) { //TODO
                    $r[]=$rule->name;
                }
            }

        }
        natcasesort($r);
        return $r;
    }


    protected function suggestHosttemplates()
    {
        $this->assertPermission('director/hosts');
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
        $this->assertPermission('director/services');
        $db = $this->db()->getDbAdapter();
        $query = $db->select()
            ->from('icinga_service', 'object_name')
            ->order('object_name')
            ->where("object_type = 'template'");
        return $db->fetchCol($query);
    }

    protected function suggestNotificationtemplates()
    {
        $this->assertPermission('director/notifications');
        $db = $this->db()->getDbAdapter();
        $query = $db->select()
            ->from('icinga_notification', 'object_name')
            ->order('object_name')
            ->where("object_type = 'template'");
        return $db->fetchCol($query);
    }

    protected function suggestCommandtemplates()
    {
        $this->assertPermission('director/commands');
        $db = $this->db()->getDbAdapter();
        $query = $db->select()
            ->from('icinga_command', 'object_name')
            ->order('object_name');
        return $db->fetchCol($query);
    }

    protected function suggestUsertemplates()
    {
        $this->assertPermission('director/users');
        $db = $this->db()->getDbAdapter();
        $query = $db->select()
            ->from('icinga_user', 'object_name')
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
        return $this->getFilterColumns('host.', [
            $this->translate('Host properties'),
            $this->translate('Custom variables')
        ]);
    }

    protected function suggestServiceFilterColumns()
    {
        return $this->getFilterColumns('host.', [
            $this->translate('Service properties'),
            $this->translate('Host properties'),
            $this->translate('Host Custom variables'),
            $this->translate('Custom variables')
        ]);
    }

    protected function getFilterColumns($prefix, $keys)
    {
        $all = IcingaService::enumProperties($this->db(), $prefix);
        $res = [];
        foreach ($keys as $key) {
            if (array_key_exists($key, $all)) {
                $res = array_merge($res, array_keys($all[$key]));
            }
        }

        natsort($res);
        return $res;
    }

    protected function suggestDependencytemplates()
    {
        $this->assertPermission('director/hosts');
        $db = $this->db()->getDbAdapter();
        $query = $db->select()
            ->from('icinga_dependency', 'object_name')
            ->order('object_name')
            ->where("object_type = 'template'");
        return $db->fetchCol($query);
    }

    protected function highlight($val, $search)
    {
        $search = ($search);
        $val = Util::escapeForHtml($val);
        return preg_replace(
            '/(' . preg_quote($search, '/') . ')/i',
            '<strong>\1</strong>',
            $val
        );
    }

    protected function getAllApplyRules()
    {
        $allApplyRules=$this->fetchAllApplyRules();
        foreach ($allApplyRules as $rule) {
            $rule->filter = Filter::fromQueryString($rule->assign_filter);
        }

        return $allApplyRules;
    }

    protected function fetchAllApplyRules()
    {
        $db = $this->db()->getDbAdapter();
        $query = $db->select()->from(
            array('s' => 'icinga_service'),
            array(
                'id'            => 's.id',
                'name'          => 's.object_name',
                'assign_filter' => 's.assign_filter',
            )
        )->where('object_type = ? AND assign_filter IS NOT NULL', 'apply');

        return $db->fetchAll($query);
    }




}
