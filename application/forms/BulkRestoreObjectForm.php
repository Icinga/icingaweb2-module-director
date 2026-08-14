<?php

namespace Icinga\Module\Director\Forms;

use Icinga\Module\Director\Objects\IcingaObject;
use Icinga\Module\Director\Web\Form\DirectorForm;

class BulkRestoreObjectForm extends DirectorForm
{
    /**
     * @var array<int, array{
     *     object: IcingaObject,
     *     lookup: ?IcingaObject,
     *     delete: bool
     * }> ordered oldest-restored-last, see ActivityLogTable multiselect order
     */
    protected $items = [];

    public function setup()
    {
        $this->addSubmitButton(sprintf(
            $this->translate('Restore %d objects'),
            count($this->items)
        ));
    }

    public function onSuccess()
    {
        $counts = [
            RestoreObjectForm::STATUS_RESTORED   => 0,
            RestoreObjectForm::STATUS_RECREATED  => 0,
            RestoreObjectForm::STATUS_UNCHANGED  => 0,
            RestoreObjectForm::STATUS_DELETED    => 0,
        ];

        foreach ($this->items as $item) {
            $result = RestoreObjectForm::restoreObject(
                $item['object'],
                $this->db,
                $item['delete'],
                $item['lookup'] ?? null
            );
            $counts[$result['status']]++;
        }

        $this->redirectOnSuccess($this->summarize($counts));
    }

    protected function summarize(array $counts): string
    {
        $parts = [];
        if ($counts[RestoreObjectForm::STATUS_RESTORED] > 0) {
            $parts[] = sprintf(
                $this->translate('%d restored'),
                $counts[RestoreObjectForm::STATUS_RESTORED]
            );
        }

        if ($counts[RestoreObjectForm::STATUS_RECREATED] > 0) {
            $parts[] = sprintf(
                $this->translate('%d re-created'),
                $counts[RestoreObjectForm::STATUS_RECREATED]
            );
        }

        if ($counts[RestoreObjectForm::STATUS_DELETED] > 0) {
            $parts[] = sprintf(
                $this->translate('%d deleted'),
                $counts[RestoreObjectForm::STATUS_DELETED]
            );
        }

        if ($counts[RestoreObjectForm::STATUS_UNCHANGED] > 0) {
            $parts[] = sprintf(
                $this->translate('%d unchanged'),
                $counts[RestoreObjectForm::STATUS_UNCHANGED]
            );
        }

        if (empty($parts)) {
            return $this->translate('Nothing to restore');
        }

        return implode(', ', $parts);
    }

    /**
     * @param array<int, array{
     *     object: IcingaObject,
     *     lookup: ?IcingaObject,
     *     delete: bool
     * }> $items ordered oldest-restored-last
     * @return $this
     */
    public function setItems(array $items)
    {
        $this->items = $items;

        return $this;
    }
}
