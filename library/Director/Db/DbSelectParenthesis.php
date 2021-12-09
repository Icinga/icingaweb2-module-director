<?php

namespace Icinga\Module\Director\Db;

class DbSelectParenthesis extends \Zend_Db_Expr
{
    protected $select;

    public function __construct(\Zend_Db_Select $select)
    {
        parent::__construct('');
        $this->select = $select;
    }

    public function getSelect()
    {
        return $this->select;
    }

    public function __toString()
    {
        return '(' . $this->select . ')';
    }
}
