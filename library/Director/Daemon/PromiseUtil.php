<?php

namespace Icinga\Module\Director\Daemon;

use React\Promise\PromiseInterface;

/**
 * Compatibility shim for react/promise v2 and v3.
 *
 * v3 renamed ->otherwise() to ->catch() and ->always() to ->finally().
 * The runtime version is determined by the icinga-php-thirdparty bundle, so we
 * probe with method_exists() rather than checking the installed package version.
 */
class PromiseUtil
{
    /**
     * Calls ->catch() (react/promise v3) or ->otherwise() (react/promise v2)
     *
     * @param PromiseInterface $promise
     * @param callable         $onRejected
     *
     * @return PromiseInterface
     */
    public static function catch(PromiseInterface $promise, callable $onRejected): PromiseInterface
    {
        if (method_exists($promise, 'catch')) {
            return $promise->catch($onRejected);
        }

        return $promise->otherwise($onRejected);
    }

    /**
     * Calls ->finally() (react/promise v3) or ->always() (react/promise v2)
     *
     * @param PromiseInterface $promise
     * @param callable         $onFulfilledOrRejected
     *
     * @return PromiseInterface
     */
    public static function finally(PromiseInterface $promise, callable $onFulfilledOrRejected): PromiseInterface
    {
        if (method_exists($promise, 'finally')) {
            return $promise->finally($onFulfilledOrRejected);
        }

        return $promise->always($onFulfilledOrRejected);
    }
}
