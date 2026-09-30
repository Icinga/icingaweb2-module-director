<?php

// SPDX-FileCopyrightText: 2026 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Icinga\Module\Director\Web\Table;

use InvalidArgumentException;

/**
 * Allowlisted direct core properties for host/service object and template lists.
 *
 * Sensitive custom variables and arbitrary SQL identifiers are intentionally
 * excluded: URL parameters must never become executable SQL or reveal secrets.
 */
class AdditionalCoreColumns
{
    private const LABELS = [
        'display_name'         => 'Display Name',
        'address'              => 'Address',
        'address6'             => 'Address6',
        'check_command'        => 'Check Command',
        'check_period'         => 'Check Period',
        'check_interval'       => 'Check Interval',
        'retry_interval'       => 'Retry Interval',
        'max_check_attempts'   => 'Max Check Attempts',
        'zone'                 => 'Zone',
        'command_endpoint'     => 'Command Endpoint',
        'enable_active_checks' => 'Active Checks',
        'enable_notifications' => 'Notifications',
        'notes'                => 'Notes',
    ];

    private const RELATIONS = [
        'check_command'    => ['icinga_command', 'check_command_id'],
        'check_period'     => ['icinga_timeperiod', 'check_period_id'],
        'zone'             => ['icinga_zone', 'zone_id'],
        'command_endpoint' => ['icinga_endpoint', 'command_endpoint_id'],
    ];

    /**
     * @return string[]
     */
    public static function parse(string $type, ?string $csv): array
    {
        if ($csv === null || trim($csv) === '') {
            return [];
        }

        $columns = array_values(array_unique(array_map('trim', explode(',', $csv))));
        if (count($columns) > 10) {
            throw new InvalidArgumentException('A maximum of 10 additional columns is supported');
        }

        foreach ($columns as $column) {
            if (
                ! isset(self::LABELS[$column])
                || ! in_array($type, ['host', 'service'], true)
                || ($type === 'service' && in_array($column, ['address', 'address6'], true))
            ) {
                throw new InvalidArgumentException('Unsupported list column: ' . $column);
            }
        }

        return $columns;
    }

    public static function label(string $column): string
    {
        return self::LABELS[$column];
    }

    public static function expression(string $column): string
    {
        // Only keys from parse() are passed here; never interpolate raw user
        // input into an SQL identifier or a correlated subquery.
        if (isset(self::RELATIONS[$column])) {
            [$table, $foreignKey] = self::RELATIONS[$column];
            return "(SELECT rel.object_name FROM $table rel WHERE rel.id = o.$foreignKey)";
        }

        return "o.$column";
    }
}
