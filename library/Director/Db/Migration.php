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
        /** @var \Zend_Db_Adapter_Pdo_Abstract $db */
        $db = $connection->getDbAdapter();

        // TODO: this is fagile and depends on accordingly written schema files:
        $queries = preg_split('/[\n\s\t]*\;[\n\s\t]+/s', $this->sql, -1, PREG_SPLIT_NO_EMPTY);

        if (empty($queries)) {
            throw new IcingaException(
                'Migration %d has no queries',
                $this->version
            );
        }

        try {
            $query = null;
            $db->beginTransaction();
            foreach ($queries as $query) {
                if (preg_match('/^OPTIMIZE /i', $query)) {
                    $db->query($query);
                } else {
                    $db->exec($query);
                }
            }
            $db->commit();
        } catch (Exception $e) {

            throw new IcingaException(
                'Migration %d failed (%s) while running %s',
                $this->version,
                $e->getMessage(),
                $query
            );
        }

        return $this;
    }
}
