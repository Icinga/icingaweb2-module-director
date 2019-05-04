<?php

namespace dipl\Web\Table\Extension;

use Icinga\Module\Director\Web\Form\IconHelper;
use Icinga\Web\Request;
use Icinga\Web\Response;
use dipl\Html\BaseHtmlElement;
use dipl\Html\Html;
use dipl\Html\HtmlString;
use Zend_Db_Select as ZfSelect;

trait ZfSortablePriority
{
    /** @var Request */
    protected $request;

    /** @var Response */
    protected $response;

    public function handleSortPriorityActions(Request $request, Response $response)
    {
        $this->request = $request;
        $this->response = $response;
        return $this;
    }

    protected function reallyHandleSortPriorityActions()
    {
        $request = $this->request;

        if ($request->isPost() && $this->hasBeenSent($request)) {
            // $this->fixPriorities();
            foreach (array_keys($request->getPost()) as $key) {
                if (substr($key, 0, 8) === 'MOVE_UP_') {
                    $id = (int) substr($key, 8);
                    $this->moveRow($id, 'up');
                }
                if (substr($key, 0, 10) === 'MOVE_DOWN_') {
                    $id = (int) substr($key, 10);
                    $this->moveRow($id, 'down');
                }
            }
            $this->response->redirectAndExit($request->getUrl());
        }
    }

    protected function hasBeenSent(Request $request)
    {
        return $request->getPost('__FORM_NAME') === $this->getUniqueFormName();
    }

    protected function addSortPriorityButtons(BaseHtmlElement $tr, $row)
    {
        $tr->add(
            Html::tag(
                'td',
                null,
                $this->createUpDownButtons($row->{$this->keyColumn})
            )
        );

        return $tr;
    }

    protected function getPriorityColumns()
    {
        return [
            'id'   => $this->keyColumn,
            'prio' => $this->priorityColumn
        ];
    }

    protected function moveRow($id, $direction)
    {
        /** @var \Zend_Db_Adapter_Abstract $db */
        $db = $this->db();
        /** @var ZfSelect $query */
        $query = $this->getQuery();
        $tableParts = $query->getPart(ZfSelect::FROM);
        $alias = key($tableParts);
        $table = $tableParts[$alias]['tableName'];

        $whereParts = $query->getPart(ZfSelect::WHERE);
        unset($query);
        if (empty($whereParts)) {
            $where = '';
        } else {
            $where = ' AND ' . implode(' ', $whereParts);
        }

        $prioCol = $this->priorityColumn;
        $keyCol = $this->keyColumn;
        $myPrio = (int) $db->fetchOne(
            $db->select()
                ->from($table, $prioCol)
                ->where("$keyCol = ?", $id)
        );

        $op = $direction === 'up' ? '<' : '>';
        $sortDir = $direction === 'up' ? 'DESC' : 'ASC';
        $query = $db->select()
            ->from([$alias => $table], $this->getPriorityColumns())
            ->where("$prioCol $op ?", $myPrio)
            ->order("$prioCol $sortDir")
            ->limit(1);

        if (! empty($whereParts)) {
            $query->where(implode(' ', $whereParts));
        }

        $next = $db->fetchRow($query);

        if ($next) {
            $sql = 'UPDATE %s %s SET %s = CASE WHEN %s = %s THEN %d ELSE %d END'
                 . ' WHERE %s IN (%s, %s)';

            $query = sprintf(
                $sql,
                $table,
                $alias,
                $prioCol,
                $keyCol,
                $id,
                (int) $next->prio,
                $myPrio,
                $keyCol,
                $id,
                (int) $next->id
            ) . $where;

            $db->query($query);
        }
    }

    protected function getSortPriorityTitle()
    {
        return Html::tag(
            'span',
            ['title' => $this->translate('Change priority')],
            $this->translate('Prio')
        );
    }

    protected function createUpDownButtons($key)
    {
        $up = $this->createIconButton(
            "MOVE_UP_$key",
            'up-big',
            $this->translate('Move up (raise priority)')
        );
        $down = $this->createIconButton(
            "MOVE_DOWN_$key",
            'down-big',
            $this->translate('Move down (lower priority)')
        );

        if ($this->isOnFirstRow()) {
            $up->getAttributes()->add('disabled', 'disabled');
        }

        if ($this->isOnLastRow()) {
            $down->getAttributes()->add('disabled', 'disabled');
        }

        return [$down, $up];
    }

    protected function createIconButton($key, $icon, $title)
    {
        return Html::tag('input', [
            'type'  => 'submit',
            'class' => 'icon-button',
            'name'  => $key,
            'title' => $title,
            'value' => IconHelper::instance()->iconCharacter($icon)
        ]);
    }

    protected function getUniqueFormName()
    {
        $parts = explode('\\', get_class($this));
        return end($parts);
    }

    protected function renderWithSortableForm()
    {
        $this->reallyHandleSortPriorityActions();

        $url = $this->request->getUrl();
        // TODO: No margin for form
        $form = Html::tag('form', [
            'action' => $url->getAbsoluteUrl(),
            'method' => 'POST'
        ], [
            Html::tag('input', [
                'type'  => 'hidden',
                'name'  => '__FORM_NAME',
                'value' => $this->getUniqueFormName()
            ]),
            new HtmlString(parent::render())
        ]);

        return $form->render();
    }
}
