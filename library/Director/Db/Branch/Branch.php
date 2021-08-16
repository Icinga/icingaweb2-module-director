<?php

namespace Icinga\Module\Director\Db\Branch;

use Icinga\Application\Icinga;
use Icinga\Module\Director\Hook\BranchSupportHook;
use Icinga\Web\Hook;
use Icinga\Web\Request;
use Icinga\Web\Session\Session;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use RuntimeException;

/**
 * Knows whether we're in a branch
 */
class Branch
{
    /** @var UuidInterface|null */
    protected $branchUuid;

    /**
     * @deprecated
     * @param Session $session
     * @return static
     */
    public static function loadForSession(Session $session)
    {
        $self = new static();
     // TODO: Load from branch if created.
        $branch = $session->get('director/branch');

        if ($branch !== null) {
            $self->branchUuid = Uuid::fromString($branch);
        }

        return $self;
    }

    /**
     * @return Branch
     */
    public static function detect()
    {
        try {
            return static::forRequest(Icinga::app()->getRequest());
        } catch (\Exception $e) {
            return new static();
        }
    }

    /**
     * @param Request $request
     * @return Branch
     */
    public static function forRequest(Request $request)
    {
        if ($hook = static::optionalHook()) {
            return $hook->getBranchForRequest($request);
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
     * @return UuidInterface|null
     */
    public function getUuid()
    {
        return $this->branchUuid;
    }
}
