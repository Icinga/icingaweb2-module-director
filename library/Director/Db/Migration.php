<?php

namespace Icinga\Module\Director\Db;

use Exception;
use Icinga\Exception\IcingaException;
use Icinga\Module\Director\Db;

class Migration
{
    /**
     * @var string
     */
    protected $sql;

    /**
     * @var int
     */
    protected $version;

    public function __construct($version, $sql)
    {
        $this->version = $version;
        $this->sql     = $sql;
    }

    public function apply(Db $connection)
    {
        $db = $connection->getDbAdapter();
        $queries = preg_split('/[\n\s\t]*\;[\n\s\t]*/s', $this->sql, -1, PREG_SPLIT_NO_EMPTY);

        if (empty($queries)) {
            throw new IcingaException(
                'Migration %d has no queries',
                $this->version
            );
        }

        try {
            $db->beginTransaction();

            foreach ($queries as $query) {
                $db->exec($query);
            }

            $db->commit();

        } catch (Exception $e) {

            $db->rollback();
            throw new IcingaException(
                'Migration %d failed: %s',
                $this->version,
                $e->getMessage()
            );
        }

        return $this;
    }
}
