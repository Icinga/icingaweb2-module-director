<?php

namespace Icinga\Module\Director\Web\Widget;

use gipfl\IcingaWeb2\Url;
use Icinga\Authentication\Auth;
use Icinga\Data\Filter\Filter;
use Icinga\Module\Director\Objects\HostApplyMatches;
use Icinga\Module\Director\Objects\IcingaHost;
use Icinga\Module\Director\Objects\IcingaService;
use Icinga\Module\Director\Repository\IcingaTemplateRepository;

class HostServiceRedirector
{
    /** @var IcingaHost */
    protected $host;

    /** @var Auth */
    protected $auth;

    /** @var IcingaHost[] */
    protected $parents;

    /** @var HostApplyMatches */
    protected $applyMatcher;

    /** @var \Icinga\Module\Director\Db */
    protected $db;

    public function __construct(IcingaHost $host, Auth $auth)
    {
        $this->host = $host;
        $this->auth = $auth;
        $this->db = $host->getConnection();
    }

    /**
     * @param $serviceName
     * @return Url
     * @throws \Icinga\Exception\NotFoundError
     */
    public function getRedirectionUrl($serviceName)
    {
        if ($this->auth->hasPermission('director/host')) {
            if ($url = $this->getSingleServiceUrl($serviceName)) {
                return $url;
            } elseif ($url = $this->getParentServiceUrl($serviceName)) {
                return $url;
            } elseif ($url = $this->getAppliedServiceUrl($serviceName)) {
                return $url;
            } elseif ($url = $this->getServiceSetServiceUrl($serviceName)) {
                return $url;
            } elseif ($url = $this->getAppliedServiceSetUrl($serviceName)) {
                return $url;
            }
        } elseif ($this->auth->hasPermission('director/monitoring/services-ro')) {
            return Url::fromPath('director/host/servicesro', [
                'name'    => $this->host->getObjectName(),
                'service' => $serviceName
            ]);
        }

        return Url::fromPath('director/host/invalidservice', [
            'name'    => $this->host->getObjectName(),
            'service' => $serviceName,
        ]);
    }

    /**
     * @return IcingaHost[]
     * @throws \Icinga\Exception\NotFoundError
     */
    protected function getParents()
    {
        if ($this->parents === null) {
            $this->parents = IcingaTemplateRepository::instanceByObject(
                $this->host
            )->getTemplatesFor($this->host, true);
        }

        return $this->parents;
    }

    /**
     * @param $serviceName
     * @return Url|null
     */
    protected function getSingleServiceUrl($serviceName)
    {
        if (IcingaService::exists([
            'host_id' => $this->host->get('id'),
            'object_name' => $serviceName
        ], $this->db)) {
            return Url::fromPath('director/service/edit', [
                'name' => $serviceName,
                'host' => $this->host->getObjectName()
            ]);
        }

        return null;
    }

    /**
     * @param $serviceName
     * @return Url|null
     * @throws \Icinga\Exception\NotFoundError
     */
    protected function getParentServiceUrl($serviceName)
    {
        foreach ($this->getParents() as $parent) {
            if (IcingaService::exists([
                'host_id'     => $parent->get('id'),
                'object_name' => $serviceName
            ], $this->db)) {
                return Url::fromPath('director/host/inheritedservice', [
                    'name'          => $this->host->getObjectName(),
                    'service'       => $serviceName,
                    'inheritedFrom' => $parent->getObjectName()
                ]);
            }
        }

        return null;
    }

    /**
     * @param $serviceName
     * @return Url|null
     * @throws \Icinga\Exception\NotFoundError
     */
    protected function getServiceSetServiceUrl($serviceName)
    {
        $ids = [$this->host->get('id')];

        foreach ($this->getParents() as $parent) {
            $ids[] = $parent->get('id');
        }

        $db = $this->db->getDbAdapter();
        $query = $db->select()
            ->from(
                ['s' => 'icinga_service'],
                ['service_set_name' => 'ss.object_name',]
            )->join(
                ['ss' => 'icinga_service_set'],
                's.service_set_id = ss.id',
                []
            )->join(
                ['hsi' => 'icinga_service_set_inheritance'],
                'hsi.parent_service_set_id = ss.id',
                []
            )->join(
                ['hs' => 'icinga_service_set'],
                'hs.id = hsi.service_set_id',
                []
            )->where('hs.host_id IN (?)', $ids)
            ->where('s.object_name = ?', $serviceName);

        if ($row = $db->fetchRow($query)) {
            return Url::fromPath('director/host/servicesetservice', [
                'name'    => $this->host->getObjectName(),
                'service' => $serviceName,
                'set'     => $row->service_set_name
            ]);
        }

        return null;
    }

    /**
     * @param $serviceName
     * @return Url|null
     */
    protected function getAppliedServiceUrl($serviceName)
    {
        $matcher = $this->getHostApplyMatcher();
        foreach ($this->fetchAllApplyRulesForService($serviceName) as $rule) {
            if ($matcher->matchesFilter($rule->filter)) {
                return Url::fromPath('director/host/appliedservice', [
                    'name'       => $this->host->getObjectName(),
                    'service_id' => $rule->id,
                ]);
            }
        }

        return null;
    }

    /**
     * @param $serviceName
     * @return Url|null
     */
    protected function getAppliedServiceSetUrl($serviceName)
    {
        $matcher = $this->getHostApplyMatcher();
        foreach ($this->fetchAllServiceSetApplyRulesForService($serviceName) as $rule) {
            if ($matcher->matchesFilter($rule->filter)) {
                return Url::fromPath('director/host/servicesetservice', [
                    'name'    => $this->host->getObjectName(),
                    'service' => $serviceName,
                    'set'     => $rule->service_set_name
                ]);
            }
        }

        return null;
    }

    protected function getHostApplyMatcher()
    {
        if ($this->applyMatcher === null) {
            $this->applyMatcher = HostApplyMatches::prepare($this->host);
        }

        return $this->applyMatcher;
    }

    protected function fetchAllApplyRulesForService($serviceName)
    {
        $db = $this->db->getDbAdapter();
        $query = $db->select()->from(
            ['s' => 'icinga_service'],
            [
                'id'            => 's.id',
                'name'          => 's.object_name',
                'assign_filter' => 's.assign_filter',
            ]
        )->where('object_name = ?', $serviceName)
        ->where('object_type = ? AND assign_filter IS NOT NULL', 'apply');

        $allRules = $db->fetchAll($query);
        foreach ($allRules as $rule) {
            $rule->filter = Filter::fromQueryString($rule->assign_filter);
        }

        return $allRules;
    }

    protected function fetchAllServiceSetApplyRulesForService($serviceName)
    {
        $db = $this->db->getDbAdapter();
        $query = $db->select()->from(
            ['s' => 'icinga_service'],
            [
                'id'            => 's.id',
                'name'          => 's.object_name',
                'assign_filter' => 'ss.assign_filter',
                'service_set_name' => 'ss.object_name',
            ]
        )->join(
            ['ss' => 'icinga_service_set'],
            's.service_set_id = ss.id',
            []
        )->where('s.object_name = ?', $serviceName)
        ->where('ss.assign_filter IS NOT NULL');

        $allRules = $db->fetchAll($query);
        foreach ($allRules as $rule) {
            $rule->filter = Filter::fromQueryString($rule->assign_filter);
        }

        return $allRules;
    }
}
