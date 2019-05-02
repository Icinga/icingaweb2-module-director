<?php

namespace Icinga\Module\Director\Web\Table;

use Icinga\Module\Director\Db;
use Icinga\Module\Director\IcingaConfig\IcingaConfig;
use gipfl\IcingaWeb2\Link;
use gipfl\IcingaWeb2\Table\ZfQueryBasedTable;

class GeneratedConfigFileTable extends ZfQueryBasedTable
{
    use DbHelper;

    protected $searchColumns = ['file_path'];

    protected $deploymentId;

    protected $activeFile;

    /** @var IcingaConfig */
    protected $config;

    public static function load(IcingaConfig $config, Db $db)
    {
        $table = new static($db);
        $table->config = $config;
        $table->getAttributes()->set('data-base-target', '_self');
        return $table;
    }

    public function renderRow($row)
    {
        $counts = implode(' / ', [
            $row->cnt_object,
            $row->cnt_template,
            $row->cnt_apply
        ]);

        $tr = $this::row([
            $this->getFileLink($row),
            $counts,
            $row->size
        ]);

        if ($row->file_path === $this->activeFile) {
            $tr->getAttributes()->add('class', 'active');
        }

        return $tr;
    }

    public function setActiveFilename($filename)
    {
        $this->activeFile = $filename;
        return $this;
    }

    protected function getFileLink($row)
    {
        $params = [
            'config_checksum' => $row->config_checksum,
            'file_path'       => $row->file_path
        ];

        if ($this->deploymentId) {
            $params['deployment_id'] = $this->deploymentId;
        }

        return Link::create($row->file_path, 'director/config/file', $params);
    }

    public function setDeploymentId($id)
    {
        if ($id) {
            $this->deploymentId = (int) $id;
        }

        return $this;
    }

    public function getColumnsToBeRendered()
    {
        return [
            $this->translate('File'),
            $this->translate('Object/Tpl/Apply'),
            $this->translate('Size'),
        ];
    }

    public function prepareQuery()
    {
        $columns = [
            'file_path'       => 'cf.file_path',
            'size'            => 'LENGTH(f.content)',
            'cnt_object'      => 'f.cnt_object',
            'cnt_template'    => 'f.cnt_template',
            'cnt_apply'       => 'f.cnt_apply',
            'cnt_all'         => "f.cnt_object || ' / ' || f.cnt_template || ' / ' || f.cnt_apply",
            'checksum'        => 'LOWER(HEX(f.checksum))',
            'config_checksum' => 'LOWER(HEX(cf.config_checksum))',
        ];

        if ($this->isPgsql()) {
            $columns['checksum'] = "LOWER(ENCODE(f.checksum, 'hex'))";
            $columns['config_checksum'] = "LOWER(ENCODE(cf.config_checksum, 'hex'))";
        }

        return $this->db()->select()->from(
            ['cf' => 'director_generated_config_file'],
            $columns
        )->join(
            ['f' => 'director_generated_file'],
            'cf.file_checksum = f.checksum',
            []
        )->where(
            'config_checksum = ?',
            $this->quoteBinary($this->config->getChecksum())
        )->order('cf.file_path ASC');
    }
}
