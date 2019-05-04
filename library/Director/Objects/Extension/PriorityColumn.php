<?php

namespace Icinga\Module\Director\Objects\Extension;

use Zend_Db_Expr as Expr;

trait PriorityColumn
{
    public function setNextPriority($prioSetColumn = null, $prioColumn = 'priority')
    {
        /** @var \Zend_Db_Adapter_Abstract $db */
        $db = $this->getDb();
        $prioValue = '(CASE WHEN MAX(priosub.priority) IS NULL THEN 1'
            . ' ELSE MAX(priosub.priority) + 1 END)';
        $query = $db->select()
            ->from(
                ['priosub' => $this->getTableName()],
                "$prioValue"
            );

        if ($prioSetColumn !== null) {
            $query->where("priosub.$prioSetColumn = ?", $this->get($prioSetColumn));
        }

        $this->set($prioColumn, new Expr('(' . $query . ')'));

        return $this;
    }

    protected function refreshPriortyProperty($prioColumn = 'priority')
    {
        /** @var \Zend_Db_Adapter_Abstract $db */
        $db = $this->getDb();
        $idCol = $this->getAutoincKeyName();
        $query = $db->select()
            ->from($this->getTableName(), $prioColumn)
            ->where("$idCol = ?", $this->get($idCol));
        $this->reallySet($prioColumn, $db->fetchOne($query));
    }
}
