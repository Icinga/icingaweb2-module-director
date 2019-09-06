<?php

namespace Icinga\Module\Director\Web\Table;

use Icinga\Module\Director\Db;
use gipfl\IcingaWeb2\Link;
use gipfl\IcingaWeb2\Table\ZfQueryBasedTable;
use gipfl\IcingaWeb2\Url;

class ChoicesTable extends ZfQueryBasedTable
{
    protected $searchColumns = ['o.object_name'];

    protected $type;

    /**
     * @param $type
     * @param Db $db
     * @return static
     */
    public static function create($type, Db $db)
    {
        $class = __NAMESPACE__ . '\\ChoicesTable' . ucfirst($type);
        if (! class_exists($class)) {
            $class = __CLASS__;
        }

        /** @var static $table */
        $table = new $class($db);
        $table->type = $type;
        return $table;
    }

    public function getType()
    {
        return $this->type;
    }

    public function getColumnsToBeRendered()
    {
        return [$this->translate('Name')];
    }

    public function renderRow($row)
    {
        $type = $this->getType();
        $url = Url::fromPath("director/templatechoice/${type}", [
            'name' => $row->object_name
        ]);

        return $this::row([
            Link::create($row->object_name, $url)
        ]);
    }

    protected function prepareQuery()
    {
        $type = $this->getType();
        $table = "icinga_${type}_template_choice";
        return $this->db()
            ->select()
            ->from(['o' => $table], 'object_name')
            ->order('o.object_name');
    }
}
