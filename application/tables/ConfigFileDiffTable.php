<?php

namespace Icinga\Module\Director\Tables;

use Icinga\Exception\ProgrammingError;
use Icinga\Module\Director\Web\Table\QuickTable;
use Icinga\Module\Director\Util;

class ConfigFileDiffTable extends QuickTable
{
    protected $leftChecksum;

    protected $rightChecksum;

    public function getColumns()
    {
        throw new ProgrammingError('Accessing getColumns() is not supported');
    }

    protected function listTableClasses()
    {
        return array_merge(array('config-diff'), parent::listTableClasses());
    }

    protected function getRowClasses($row)
    {
        return 'file-' . $row->file_action;
    }

    protected function getActionUrl($row)
    {
        $params = array('file_path' => $row->file_path);

        if ($row->file_checksum_left === $row->file_checksum_right) {
            $params['config_checksum'] = $row->config_checksum_right;
        } elseif ($row->file_checksum_left === null) {
            $params['config_checksum'] = $row->config_checksum_right;
        } elseif ($row->file_checksum_right === null) {
            $params['config_checksum'] = $row->config_checksum_left;
        } else {
            $params['left']  = $row->config_checksum_left;
            $params['right'] = $row->config_checksum_right;
            return $this->url('director/config/filediff', $params);
        }

        return $this->url('director/config/file', $params);
    }

    public function setLeftChecksum($checksum)
    {
        $this->leftChecksum = $checksum;
        return $this;
    }

    public function setRightChecksum($checksum)
    {
        $this->rightChecksum = $checksum;
        return $this;
    }

    public function getTitles()
    {
        $view = $this->view();
        return array(
            'file_action' => $view->translate('Action'),
            'file_path'   => $view->translate('File'),
        );
    }

    public function count()
    {
        $db = $this->connection()->getConnection();
        $query = clone($this->getBaseQuery());
        $query->reset('order');
        $this->applyFiltersToQuery($query);
        return $db->fetchOne($db->select()->from(
            array('cntsub' => $query),
            array('cnt' => 'COUNT(*)')
        ));
    }

    public function fetchData()
    {
        $db = $this->connection()->getConnection();
        $query = $this->getBaseQuery();

        if ($this->hasLimit() || $this->hasOffset()) {
            $query->limit($this->getLimit(), $this->getOffset());
        }

        $this->applyFiltersToQuery($query);

        return $db->fetchAll($query);
    }

    public function getBaseQuery()
    {
        $conn = $this->connection();
        $db = $conn->getConnection();

        $left = $db->select()
            ->from(
                array('cfl' => 'director_generated_config_file'),
                array(
                    'file_path' => 'COALESCE(cfl.file_path, cfr.file_path)',
                    'config_checksum_left'  => $conn->dbHexFunc('cfl.config_checksum'),
                    'config_checksum_right' => $conn->dbHexFunc('cfr.config_checksum'),
                    'file_checksum_left'    => $conn->dbHexFunc('cfl.file_checksum'),
                    'file_checksum_right'   => $conn->dbHexFunc('cfr.file_checksum'),
                    'file_action'           => '(CASE WHEN cfr.config_checksum IS NULL'
                        . " THEN 'removed' WHEN cfl.file_checksum = cfr.file_checksum"
                        . " THEN 'unmodified' ELSE 'modified' END)",
                )
            )->joinLeft(
                array('cfr' => 'director_generated_config_file'),
                $db->quoteInto(
                    'cfl.file_path = cfr.file_path AND cfr.config_checksum = ?',
                    $conn->quoteBinary(Util::hex2binary($this->rightChecksum))
                ),
                array()
            )->where(
                'cfl.config_checksum = ?',
                $conn->quoteBinary(Util::hex2binary($this->leftChecksum))
            );

        $right = $db->select()
            ->from(
                array('cfl' => 'director_generated_config_file'),
                array(
                    'file_path' => 'COALESCE(cfr.file_path, cfl.file_path)',
                    'config_checksum_left'  => $conn->dbHexFunc('cfl.config_checksum'),
                    'config_checksum_right' => $conn->dbHexFunc('cfr.config_checksum'),
                    'file_checksum_left'    => $conn->dbHexFunc('cfl.file_checksum'),
                    'file_checksum_right'   => $conn->dbHexFunc('cfr.file_checksum'),
                    'file_action'           => "('created')",
                )
            )->joinRight(
                array('cfr' => 'director_generated_config_file'),
                $db->quoteInto(
                    'cfl.file_path = cfr.file_path AND cfl.config_checksum = ?',
                    $conn->quoteBinary(Util::hex2binary($this->leftChecksum))
                ),
                array()
            )->where(
                'cfr.config_checksum = ?',
                $conn->quoteBinary(Util::hex2binary($this->rightChecksum))
            )->where('cfl.file_checksum IS NULL');

        return $db->select()->union(array($left, $right))->order('file_path');
    }
}
