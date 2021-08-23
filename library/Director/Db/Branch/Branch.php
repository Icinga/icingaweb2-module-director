<?php

namespace Icinga\Module\Director\Db\Branch;

use Icinga\Application\Icinga;
use Icinga\Authentication\Auth;
use Icinga\Module\Director\Db;
use Icinga\Module\Director\Hook\BranchSupportHook;
use Icinga\Web\Hook;
use Icinga\Web\Request;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use RuntimeException;
use stdClass;

/**
 * Knows whether we're in a branch
 */
class Branch
{
    /** @var UuidInterface|null */
    protected $branchUuid;

    /** @var string */
    protected $name;

    /** @var string */
    protected $owner;

    /** @var @var string */
    protected $description;

    /** @var  bool */
    protected $shouldBeMerged;

    /** @var int */
    protected $cntActivities;

    public static function fromDbRow(stdClass $row)
    {
        $self = new static;
        $self->branchUuid = Uuid::fromBytes($row->uuid);
        $self->name = $row->branch_name;
        $self->owner = $row->owner;
        $self->shouldBeMerged = $row->should_be_merged === 'y';
        if (isset($row->cnt_activities)) {
            $self->cntActivities = $row->cnt_activities;
        } else {
            $self->cntActivities = 0;
        }

        return $self;
    }

    /**
     * @return Branch
     */
    public static function detect(BranchStore $store)
    {
        try {
            return static::forRequest(Icinga::app()->getRequest(), $store, Auth::getInstance());
        } catch (\Exception $e) {
            return new static();
        }
    }

    /**
     * @param Request $request
     * @param Db $db
     * @param Auth $auth
     * @return Branch
     */
    public static function forRequest(Request $request, BranchStore $store, Auth $auth)
    {
        if ($hook = static::optionalHook()) {
            return $hook->getBranchForRequest($request, $store, $auth);
        }

        return new Branch;
    }

    /**
     * @return BranchSupportHook
     */
    public static function requireHook()
    {
        if ($hook = static::optionalHook()) {
            return $hook;
        }

        throw new RuntimeException('BranchSupport Hook requested where not available');
    }

    /**
     * @return BranchSupportHook|null
     */
    public static function optionalHook()
    {
        return Hook::first('director/BranchSupport');
    }

    /**
     * @param UuidInterface $uuid
     * @return Branch
     */
    public static function withUuid(UuidInterface $uuid)
    {
        $self = new static();
        $self->branchUuid = $uuid;
        return $self;
    }

    /**
     * @return bool
     */
    public function isBranch()
    {
        return $this->branchUuid !== null;
    }

    /**
     * @return bool
     */
    public function isMain()
    {
        return $this->branchUuid === null;
    }

    /**
     * @return bool
     */
    public function shouldBeMerged()
    {
        return $this->shouldBeMerged;
    }

    /**
     * @return bool
     */
    public function isEmpty()
    {
        return $this->cntActivities === 0;
    }

    /**
     * @return int
     */
    public function getActivityCount()
    {
        return $this->cntActivities;
    }

    /**
     * @return UuidInterface|null
     */
    public function getUuid()
    {
        return $this->branchUuid;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @return string
     */
    public function getOwner()
    {
        return $this->owner;
    }
}
