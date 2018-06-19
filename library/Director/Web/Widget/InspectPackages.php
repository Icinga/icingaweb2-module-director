<?php

namespace Icinga\Module\Director\Web\Widget;

use gipfl\IcingaWeb2\Link;
use gipfl\Translation\TranslationHelper;
use Icinga\Module\Director\Db;
use Icinga\Module\Director\Objects\IcingaEndpoint;
use ipl\Html\Html;
use ipl\Html\Table;

class InspectPackages
{
    use TranslationHelper;

    /** @var Db */
    protected $db;

    /** @var string */
    protected $baseUrl;

    public function __construct(Db $db, $baseUrl)
    {
        $this->db = $db;
        $this->baseUrl = $baseUrl;
    }

    public function getContent(IcingaEndpoint $endpoint = null, $package = null, $stage = null, $file = null)
    {
        if ($endpoint === null) {
            return $this->getRootEndpoints();
        } elseif ($package === null) {
            return $this->getPackages($endpoint);
        } elseif ($stage === null) {
            return $this->getStages($endpoint, $package);
        } elseif ($file === null) {
            return $this->getFiles($endpoint, $package, $stage);
        } else {
            return $this->getFile($endpoint, $package, $stage, $file);
        }
    }

    public function getTitle(IcingaEndpoint $endpoint = null, $package = null, $stage = null, $file = null)
    {
        if ($endpoint === null) {
            return $this->translate('Endpoint in your Root Zone');
        } elseif ($package === null) {
            return \sprintf($this->translate('Packages on Endpoint: %s'), $endpoint->getObjectName());
        } elseif ($stage === null) {
            return \sprintf($this->translate('Stages in Package: %s'), $package);
        } elseif ($file === null) {
            return \sprintf($this->translate('Files in Stage: %s'), $stage);
        } else {
            return \sprintf($this->translate('File Content: %s'), $file);
        }
    }

    public function getBreadCrumb(IcingaEndpoint $endpoint = null, $package = null, $stage = null)
    {
        $parts = [
            'endpoint' => $endpoint === null ? null : $endpoint->getObjectName(),
            'package'  => $package,
            'stage'    => $stage,
        ];

        $params = [];
        // No root zone link for now:
        // $result = [Link::create($this->translate('Root Zone'), $this->baseUrl)];
        $result = [Html::tag('a', ['href' => '#'], $this->translate('Root Zone'))];
        foreach ($parts as $name => $value) {
            if ($value === null) {
                break;
            }
            $params[$name] = $value;
            $result[] = Link::create($value, $this->baseUrl, $params);
        }

        return Html::tag('ul', ['class' => 'breadcrumb'], Html::wrapEach($result, 'li'));
    }

    protected function getRootEndpoints()
    {
        $table = $this->prepareTable();
        foreach ($this->db->getEndpointNamesInDeploymentZone() as $name) {
            $table->add(Table::row([
                Link::create($name, $this->baseUrl, [
                    'endpoint' => $name,
                ])
            ]));
        }

        return $table;
    }

    protected function getPackages(IcingaEndpoint $endpoint)
    {
        $table = $this->prepareTable();
        $api = $endpoint->api();
        foreach ($api->getPackages() as $package) {
            $table->add(Table::row([
                Link::create($package->name, $this->baseUrl, [
                    'endpoint' => $endpoint->getObjectName(),
                    'package'  => $package->name,
                ])
            ]));
        }

        return $table;
    }

    protected function getStages(IcingaEndpoint $endpoint, $packageName)
    {
        $table = $this->prepareTable();
        $api = $endpoint->api();
        foreach ($api->getPackages() as $package) {
            if ($package->name !== $packageName) {
                continue;
            }
            foreach ($package->stages as $stage) {
                $label = [$stage];
                if ($stage === $package->{'active-stage'}) {
                    $label[] = Html::tag('small', [' (', $this->translate('active'), ')']);
                }

                $table->add(Table::row([
                    Link::create($label, $this->baseUrl, [
                        'endpoint' => $endpoint->getObjectName(),
                        'package'  => $package->name,
                        'stage'     => $stage
                    ])
                ]));
            }
        }

        return $table;
    }

    protected function getFiles(IcingaEndpoint $endpoint, $package, $stage)
    {
        $table = $this->prepareTable();
        $table->getAttributes()->set('data-base-target', '_next');
        foreach ($endpoint->api()->listStageFiles($stage, $package) as $filename) {
            $table->add($table->row([
                Link::create($filename, $this->baseUrl, [
                    'endpoint' => $endpoint->getObjectName(),
                    'package'  => $package,
                    'stage'    => $stage,
                    'file'     => $filename
                ])
            ]));
        }

        return $table;
    }

    protected function getFile(IcingaEndpoint $endpoint, $package, $stage, $file)
    {
        return Html::tag('pre', $endpoint->api()->getStagedFile($stage, $file, $package));
    }

    protected function prepareTable($headerCols = [])
    {
        $table = new Table();
        $table->addAttributes([
            'class' => ['common-table', 'table-row-selectable'],
            'data-base-target' => '_self'
        ]);
        if (! empty($headerCols)) {
            $table->add($table::row($headerCols, null, 'th'));
        }

        return $table;
    }
}
