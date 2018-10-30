<?php

namespace Icinga\Module\Director\Web\Table;

use Icinga\Module\Director\Db;
use Icinga\Module\Director\Util;
use dipl\Html\Link;
use dipl\Web\Table\ZfQueryBasedTable;

class ConfigFileDiffTable extends ZfQueryBasedTable
{
    use DbHelper;

    protected $leftChecksum;

    protected $rightChecksum;

    /**
     * @param $leftSum
     * @param $rightSum
     * @param Db $connection
     * @return static
     */
    public static function load($leftSum, $rightSum, Db $connection)
    {
        $table = new static($connection);
        $table->getAttributes()->add('class', 'config-diff');
        return $table->setLeftChecksum($leftSum)
            ->setRightChecksum($rightSum);
    }

    public function renderRow($row)
    {
        $tr = $this::row([
            $this->getFileFiffLink($row),
            $row->file_path,
        ]);

        $tr->getAttributes()->add('class', 'file-' . $row->file_action);
        return $tr;
    }

    protected function getFileFiffLink($row)
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
            return Link::create(
                $row->file_action,
                'director/config/filediff',
                $params
            );
        }

        return Link::create($row->file_action, 'director/config/file', $params);
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
        return array(
            $this->translate('Action'),
            $this->translate('File'),
        );
    }

    public function prepareQuery()
    {
        $db = $this->db();

        $left = $db->select()
            ->from(
                array('cfl' => 'director_generated_config_file'),
                array(
                    'file_path' => 'COALESCE(cfl.file_path, cfr.file_path)',
                    'config_checksum_left'  => $this->dbHexFunc('cfl.config_checksum'),
                    'config_checksum_right' => $this->dbHexFunc('cfr.config_checksum'),
                    'file_checksum_left'    => $this->dbHexFunc('cfl.file_checksum'),
                    'file_checksum_right'   => $this->dbHexFunc('cfr.file_checksum'),
                    'file_action'           => '(CASE WHEN cfr.config_checksum IS NULL'
                        . " THEN 'removed' WHEN cfl.file_checksum = cfr.file_checksum"
                        . " THEN 'unmodified' ELSE 'modified' END)",
                )
            )->joinLeft(
                array('cfr' => 'director_generated_config_file'),
                $db->quoteInto(
                    'cfl.file_path = cfr.file_path AND cfr.config_checksum = ?',
                    $this->quoteBinary(hex2bin($this->rightChecksum))
                ),
                array()
            )->where(
                'cfl.config_checksum = ?',
                $this->quoteBinary(hex2bin($this->leftChecksum))
            );

        $right = $db->select()
            ->from(
                array('cfl' => 'director_generated_config_file'),
                array(
                    'file_path' => 'COALESCE(cfr.file_path, cfl.file_path)',
                    'config_checksum_left'  => $this->dbHexFunc('cfl.config_checksum'),
                    'config_checksum_right' => $this->dbHexFunc('cfr.config_checksum'),
                    'file_checksum_left'    => $this->dbHexFunc('cfl.file_checksum'),
                    'file_checksum_right'   => $this->dbHexFunc('cfr.file_checksum'),
                    'file_action'           => "('created')",
                )
            )->joinRight(
                array('cfr' => 'director_generated_config_file'),
                $db->quoteInto(
                    'cfl.file_path = cfr.file_path AND cfl.config_checksum = ?',
                    $this->quoteBinary(hex2bin($this->leftChecksum))
                ),
                array()
            )->where(
                'cfr.config_checksum = ?',
                $this->quoteBinary(hex2bin($this->rightChecksum))
            )->where('cfl.file_checksum IS NULL');

        return $db->select()->union(array($left, $right))->order('file_path');
    }
}
