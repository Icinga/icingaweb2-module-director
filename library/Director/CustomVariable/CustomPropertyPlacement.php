<?php

// SPDX-FileCopyrightText: 2026 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Icinga\Module\Director\CustomVariable;

/**
 * Places each custom property an object reaches under the one source it is shown with
 *
 * @phpstan-import-type Attachment from CustomPropertySource
 *
 * @phpstan-type PlacedProperty array{
 *     uuid: string,
 *     key_name: string,
 *     value_type: string,
 *     label: ?string,
 *     source: CustomPropertySource,
 *     allow_removal: bool,
 *     required: bool
 * }
 */
class CustomPropertyPlacement
{
    /**
     * Place every property that the given sources provide under one of them
     *
     * The object's own attachment wins. Next comes the template that the
     * object inherits the property's value from, then the template imported
     * last that attaches the property. A command gets the property only when
     * neither the object nor one of its templates attaches it.
     *
     * A property is removable only where the object attaches it itself. With
     * an own attachment, its required flag applies. Otherwise the property is
     * required when one of the object's templates requires it. Templates and
     * plain commands imported by a command count as its templates. The check
     * command of a host or service never makes a property required.
     *
     * @param CustomPropertySource[] $sources      The sources of one object, the object itself first,
     *   see {@see CustomPropertySources::getSources()}
     * @param array<string, string>  $valueOrigins Names of the templates the object inherits each value from,
     *   comma separated when several templates contribute to it
     *
     * @return array<string, PlacedProperty> Keyed by property name, in no particular order
     */
    public static function place(array $sources, array $valueOrigins = []): array
    {
        $own = array_shift($sources);
        if ($own === null) {
            return [];
        }

        $result = [];
        foreach ($own->attachments as $name => $attachment) {
            $result[$name] = array_merge($attachment, ['source' => $own, 'allow_removal' => true]);
        }

        $templatesByName = [];
        foreach ($sources as $source) {
            if ($source->type !== $own->type) {
                continue;
            }

            foreach ($source->attachments as $name => $attachment) {
                $templatesByName[$name][] = $source;
            }
        }

        foreach ($templatesByName as $name => $templates) {
            if (isset($result[$name])) {
                continue;
            }

            $origins = explode(', ', $valueOrigins[$name] ?? '');
            $placedUnder = end($templates);
            $required = false;
            foreach ($templates as $template) {
                $required = $required || $template->attachments[$name]['required'];
                if (in_array($template->name, $origins, true)) {
                    $placedUnder = $template;
                }
            }

            $result[$name] = array_merge(
                $placedUnder->attachments[$name],
                ['source' => $placedUnder, 'allow_removal' => false, 'required' => $required]
            );
        }

        foreach ($sources as $source) {
            if ($source->type === $own->type) {
                continue;
            }

            foreach ($source->attachments as $name => $attachment) {
                $result[$name] ??= array_merge(
                    $attachment,
                    ['source' => $source, 'allow_removal' => false, 'required' => false]
                );
            }
        }

        return $result;
    }
}
