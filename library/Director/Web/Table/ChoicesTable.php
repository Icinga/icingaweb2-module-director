<?php

namespace Icinga\Module\Director\Web\Table;

use Icinga\Module\Director\Db;
use ipl\Html\Link;
use ipl\Web\Component\ControlsAndContent;
use ipl\Web\Table\ZfQueryBasedTable;
use ipl\Web\Url;

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

    public function renderTo(ControlsAndContent $controller)
    {
        $url = $controller->url();
        $this->initializeOptionalQuickSearch($controller);
        $controller->content()->add([
            $this->getPaginator($url),
            $this
        ]);

        if ($url->getParam('format') === 'sql') {
            $controller->content()->prepend($this->dumpSqlQuery($url));
        }
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

        return $this::tr(
            $this::td(Link::create($row->object_name, $url))
        );
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
