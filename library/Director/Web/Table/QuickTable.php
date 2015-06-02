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
        $firstRow = true;

        foreach ($this->getTitles() as $key => $title) {
            $val = $row->$key;
            $value = null;

            if ($firstRow) {
                if ($val !== null && $url = $this->getActionUrl($row)) {
                    $value = $this->view()->qlink($val, $this->getActionUrl($row));
                }
                $firstRow = false;
            }

            if ($value === null) {
                $value = $val === null ? '-' : $this->view()->escape($val);
            }

            $htm .= '    <td>' . $value . "</td>\n";
        }

        if ($this->hasAdditionalActions()) {
            $htm .= '    <td class="actions">' . $this->renderAdditionalActions($row) . "</td>\n";
        }

        return $htm . "  </tr>\n";
    }

    abstract protected function getTitles();

    protected function getActionUrl($row)
    {
        return false;
    }

    public function setConnection(Selectable $connection)
    {
        $this->connection = $connection;
        return $this;
    }

    public function hasAdditionalActions()
    {
        return method_exists($this, 'renderAdditionalActions');
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

        if ($this->hasAdditionalActions()) {
            $htm .= '    <th class="actions">' . $view->translate('Actions') . "</th>\n";
        }

        return $htm . "  </tr>\n</thead>\n";
    }

    protected function url($url, $params)
    {
        return Url::fromPath($url, $params);
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
