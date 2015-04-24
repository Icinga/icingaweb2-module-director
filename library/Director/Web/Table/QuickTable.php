<?php

namespace Icinga\Module\Director\Web\Table;

use Icinga\Application\Icinga;
use Icinga\Data\Selectable;
use Icinga\Web\Request;
use Icinga\Web\Url;

abstract class QuickTable
{
    protected $view;

    protected $connection;

    protected function renderRow($row)
    {
        $htm = "  <tr>\n";
        $idKey = key($row);
        $id = $row->$idKey;
        unset($row->$idKey);

        foreach ($row as $key => $val) {
            $htm .= '    <td>' . ($val === null ? '-' : $this->view()->escape($val)) . "</td>\n";
        }
        $htm .= '    <td class="actions">' . $this->getActionLinks($id) . "</td>\n";
        return $htm . "  </tr>\n";
    }

    public function setConnection(Selectable $connection)
    {
        $this->connection = $connection;
        return $this;
    }

    protected function connection()
    {
        // TODO: Fail if missing? Require connection in constructor?
        return $this->connection;
    }

    protected function renderTitles($row)
    {
        $view = $this->view;
        $htm = "<thead>\n  <tr>\n";
        foreach ($row as $title) {
            $htm .= '    <th>' . $view->escape($title) . "</th>\n";
        }
        $htm .= '    <th class="actions">' . $view->translate('Actions') . "</th>\n";
        return $htm . "  </tr>\n</thead>\n";
    }

    public function render()
    {
        $data = $this->fetchData();

        $htm = '<table class="simple action">' . "\n"
             . $this->renderTitles($this->getTitles())
             . "<tbody>\n";
        foreach ($data as $row) {
            $htm .= $this->renderRow($row);
        }
        return $htm . "</tbody>\n</table>\n";
    }

    protected function view()
    {
        if ($this->view === null) {
            $this->view = Icinga::app()->getViewRenderer()->view;
        }
        return $this->view;
    }


    public function setView($view)
    {
        $this->view = $view;
    }

    public function __toString()
    {
        return $this->render();
    }
}
