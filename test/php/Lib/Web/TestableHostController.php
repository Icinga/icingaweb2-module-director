<?php

// SPDX-FileCopyrightText: 2026 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Tests\Icinga\Module\Director\Lib\Web;

use Icinga\Module\Director\Controllers\HostController;
use Icinga\Module\Director\Db;
use Icinga\Module\Director\Web\Controller\ActionController;
use ReflectionClass;
use ReflectionProperty;

/**
 * Host controller without a request that lets every user pass the permission checks
 * and exposes the multipart parts the actions queue
 */
class TestableHostController extends HostController
{
    public static function create(Db $db): static
    {
        $controller = (new ReflectionClass(static::class))->newInstanceWithoutConstructor();
        (new ReflectionProperty(ActionController::class, 'db'))->setValue($controller, $db);
        // The request is missing, so the type cannot come from the controller name
        $controller->type = 'host';

        return $controller;
    }

    public function hasPermission($permission)
    {
        return true;
    }

    /**
     * Get the queued parts, keyed by the id of the container they replace
     *
     * @return array<string, string> The rendered content of each part
     */
    public function getPartsById(): array
    {
        $parts = [];
        foreach ($this->parts as $part) {
            [$target, $content] = explode("\n", $part->render(), 2) + [1 => ''];
            $parts[substr($target, strlen('for='))] = $content;
        }

        return $parts;
    }
}
