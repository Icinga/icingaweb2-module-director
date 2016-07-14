<?php

namespace Icinga\Module\Director\Web\Table;

use Icinga\Application\Icinga;
use Icinga\Data\Filter\FilterAnd;
use Icinga\Data\Filter\FilterChain;
use Icinga\Data\Filter\FilterNot;
use Icinga\Data\Filter\FilterOr;
use Icinga\Data\Filter\Filter;
use Icinga\Data\Selectable;
use Icinga\Data\Paginatable;
use Icinga\Exception\QueryException;
use Icinga\Module\Director\PlainObjectRenderer;
use Icinga\Web\Request;
use Icinga\Web\Url;
use Icinga\Web\Widget;
use Icinga\Web\Widget\Paginator;
use stdClass;

abstract class QuickTable implements Paginatable
{
    protected $view;

    protected $connection;

    protected $limit;

    protected $offset;

    protected $filter;

    protected $enforcedFilters = array();

    protected $searchColumns = array();

    protected function getRowClasses($row)
    {
        return array();
    }

    protected function getRowClassesString($row)
    {
        return $this->createClassAttribute($this->getRowClasses($row));
    }

    protected function createClassAttribute($classes)
    {
        $str = $this->createClassesString($classes);
        if (strlen($str) > 0) {
            return ' class="' . $str . '"';
        } else {
            return '';
        }
    }

    private function createClassesString($classes)
    {
        if (is_string($classes)) {
            $classes = array($classes);
        }

        if (empty($classes)) {
            return '';
        } else {
            return implode(' ', $classes);
        }
    }

    protected function renderRow($row)
    {
        $htm = "  <tr" . $this->getRowClassesString($row) . ">\n";
        $firstCol = true;

        foreach ($this->getTitles() as $key => $title) {

            // Support missing columns
            if (property_exists($row, $key)) {
                $val = $row->$key;
            } else {
                $val = null;
            }

            $value = null;

            if ($firstCol) {
                if ($val !== null && $url = $this->getActionUrl($row)) {
                    $value = $this->view()->qlink($val, $this->getActionUrl($row));
                }
                $firstCol = false;
            }

            if ($value === null) {
                if ($val === null) {
                    $value = '-';
                } elseif (is_array($val) || $val instanceof stdClass) {
                    $value = '<pre>'
                           . $this->view()->escape(PlainObjectRenderer::render($val))
                           . '</pre>';
                } else {
                    $value = $this->view()->escape($val);
                }
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

    abstract protected function getBaseQuery();

    public function fetchData()
    {
        $db = $this->connection()->getConnection();
        $query = $this->getBaseQuery()->columns($this->getColumns());

        if ($this->hasLimit() || $this->hasOffset()) {
            $query->limit($this->getLimit(), $this->getOffset());
        }

        $this->applyFiltersToQuery($query);

        return $db->fetchAll($query);
    }

    protected function applyFiltersToQuery($query)
    {
        $filter = null;
        $enforced = $this->enforcedFilters;
        if ($this->filter && ! $this->filter->isEmpty()) {
            $filter = $this->filter;
        } elseif (! empty($enforced)) {
            $filter = array_shift($enforced);
        }
        if ($filter) {
            foreach ($enforced as $f) {
                $filter->andFilter($f);
            }
            $query->where($this->renderFilter($filter));
        }

        return $query;
    }

    public function getPaginator()
    {
        $paginator = new Paginator();
        $paginator->setQuery($this);

        return $paginator;
    }

    public function count()
    {
        $db = $this->connection()->getConnection();
        $query = clone($this->getBaseQuery());
        $query->reset('order')->columns(array('COUNT(*)'));
        $this->applyFiltersToQuery($query);

        return $db->fetchOne($query);
    }

    public function limit($count = null, $offset = null)
    {
        $this->limit = $count;
        $this->offset = $offset;

        return $this;
    }

    public function hasLimit()
    {
        return $this->limit !== null;
    }

    public function getLimit()
    {
        return $this->limit;
    }

    public function hasOffset()
    {
        return $this->offset !== null;
    }

    public function getOffset()
    {
        return $this->offset;
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

    protected function db()
    {
        return $this->connection()->getConnection();
    }

    protected function renderTitles($row)
    {
        $view = $this->view();
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

    protected function listTableClasses()
    {
        return array('simple', 'common-table', 'table-row-selectable');
    }

    public function render()
    {
        $data = $this->fetchData();

        $htm = '<table' . $this->createClassAttribute($this->listTableClasses()) . '>' . "\n"
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

    protected function getSearchColumns()
    {
        return $this->searchColumns;
    }

    abstract public function getColumns();

    public function getFilterColumns()
    {
        $keys = array_keys($this->getColumns());
        return array_combine($keys, $keys);
    }

    public function setFilter($filter)
    {
        $this->filter = $filter;
        return $this;
    }

    public function enforceFilter($filter, $expression = null)
    {
        if (! $filter instanceof Filter) {
            $filter = Filter::where($filter, $expression);
        }
        $this->enforcedFilters[] = $filter;
        return $this;
    }

    public function getFilterEditor(Request $request)
    {
        $filterEditor = Widget::create('filterEditor')
            ->setColumns(array_keys($this->getColumns()))
            ->setSearchColumns($this->getSearchColumns())
            ->preserveParams('limit', 'sort', 'dir', 'view', 'backend', '_dev')
            ->ignoreParams('page')
            ->handleRequest($request);

        $filter = $filterEditor->getFilter();
        $this->setFilter($filter);

        return $filterEditor;
    }

    protected function mapFilterColumn($col)
    {
        $cols = $this->getColumns();
        return $cols[$col];
    }

    protected function renderFilter($filter, $level = 0)
    {
        $str = '';
        if ($filter instanceof FilterChain) {
            if ($filter instanceof FilterAnd) {
                $op = ' AND ';
            } elseif ($filter instanceof FilterOr) {
                $op = ' OR ';
            } elseif ($filter instanceof FilterNot) {
                $op = ' AND ';
                $str .= ' NOT ';
            } else {
                throw new QueryException(
                    'Cannot render filter: %s',
                    $filter
                );
            }
            $parts = array();
            if (! $filter->isEmpty()) {
                foreach ($filter->filters() as $f) {
                    $filterPart = $this->renderFilter($f, $level + 1);
                    if ($filterPart !== '') {
                        $parts[] = $filterPart;
                    }
                }
                if (! empty($parts)) {
                    if ($level > 0) {
                        $str .= ' (' . implode($op, $parts) . ') ';
                    } else {
                        $str .= implode($op, $parts);
                    }
                }
            }
        } else {
            $str .= $this->whereToSql(
                $this->mapFilterColumn($filter->getColumn()),
                $filter->getSign(),
                $filter->getExpression()
            );
        }

        return $str;
    }

    protected function escapeForSql($value)
    {
        // bindParam? bindValue?
        if (is_array($value)) {
            $ret = array();
            foreach ($value as $val) {
                $ret[] = $this->escapeForSql($val);
            }
            return implode(', ', $ret);
        } else {
            //if (preg_match('/^\d+$/', $value)) {
            //    return $value;
            //} else {
            return $this->db()->quote($value);
            //}
        }
    }

    protected function escapeWildcards($value)
    {
        return preg_replace('/\*/', '%', $value);
    }

    protected function valueToTimestamp($value)
    {
        // We consider integers as valid timestamps. Does not work for URL params
        if (ctype_digit($value)) {
            return $value;
        }
        $value = strtotime($value);
        if (! $value) {
            /*
            NOTE: It's too late to throw exceptions, we might finish in __toString
            throw new QueryException(sprintf(
                '"%s" is not a valid time expression',
                $value
            ));
            */
        }
        return $value;
    }

    protected function timestampForSql($value)
    {
        // TODO: do this db-aware
        return $this->escapeForSql(date('Y-m-d H:i:s', $value));
    }

    /**
     * Check for timestamp fields
     *
     * TODO: This is not here to do automagic timestamp stuff. One may
     *       override this function for custom voodoo, IdoQuery right now
     *       does. IMO we need to split whereToSql functionality, however
     *       I'd prefer to wait with this unless we understood how other
     *       backends will work. We probably should also rename this
     *       function to isTimestampColumn().
     *
     * @param  string $field Field Field name to checked
     * @return bool          Whether this field expects timestamps
     */
    public function isTimestamp($field)
    {
        return false;
    }

    public function whereToSql($col, $sign, $expression)
    {
        if ($this->isTimestamp($col)) {
            $expression = $this->valueToTimestamp($expression);
        }

        if (is_array($expression) && $sign === '=') {
            // TODO: Should we support this? Doesn't work for blub*
            return $col . ' IN (' . $this->escapeForSql($expression) . ')';
        } elseif ($sign === '=' && strpos($expression, '*') !== false) {
            if ($expression === '*') {
                // We'll ignore such filters as it prevents index usage and because "*" means anything, anything means
                // all whereas all means that whether we use a filter to match anything or no filter at all makes no
                // difference, except for performance reasons...
                return '';
            }

            return $col . ' LIKE ' . $this->escapeForSql($this->escapeWildcards($expression));
        } elseif ($sign === '!=' && strpos($expression, '*') !== false) {
            if ($expression === '*') {
                // We'll ignore such filters as it prevents index usage and because "*" means nothing, so whether we're
                // using a real column with a valid comparison here or just an expression which cannot be evaluated to
                // true makes no difference, except for performance reasons...
                return $this->escapeForSql(0);
            }

            return $col . ' NOT LIKE ' . $this->escapeForSql($this->escapeWildcards($expression));
        } else {
            return $col . ' ' . $sign . ' ' . $this->escapeForSql($expression);
        }
    }
}
