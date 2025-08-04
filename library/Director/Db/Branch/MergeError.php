<?php

namespace Icinga\Module\Director\Db\Branch;

use Exception;
use ipl\I18n\Translation;

abstract class MergeError extends Exception
{
    use Translation;

    /** @var BranchActivity */
    protected $activity;

    public function __construct(BranchActivity $activity)
    {
        $this->activity = $activity;
        parent::__construct($this->prepareMessage());
    }

    abstract protected function prepareMessage();

    public function getObjectTypeName()
    {
        return preg_replace('/^icinga_/', '', $this->getActivity()->getObjectTable());
    }

    public function getNiceObjectName()
    {
        return $this->activity->getObjectName();
    }

    public function getActivity()
    {
        return $this->activity;
    }
}
