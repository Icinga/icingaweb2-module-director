<?php

// SPDX-FileCopyrightText: 2026 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Icinga\Module\Director\CustomVariable;

/**
 * An object whose property attachments another object reaches
 *
 * @phpstan-type Attachment array{uuid: string, key_name: string, value_type: string, label: ?string, required: bool}
 */
readonly class CustomPropertySource
{
    /**
     * Create a new CustomPropertySource
     *
     * @param CustomPropertySourceKind $kind        How the object reaches this source
     * @param string                   $type        Short table name of the source, e.g. "host" or "command"
     * @param ?int                     $id          Id of the source, null for an object that is not stored yet
     * @param string                   $name        Object name of the source
     * @param bool                     $isTemplate  Whether the source is a template
     * @param array<string, Attachment> $attachments Root properties attached to the source, keyed by name, with
     *   their binary uuid
     */
    public function __construct(
        public CustomPropertySourceKind $kind,
        public string $type,
        public ?int $id,
        public string $name,
        public bool $isTemplate,
        public array $attachments
    ) {
    }

    /**
     * Get the key that identifies this source among the sources of any object
     *
     * @return string
     */
    public function getKey(): string
    {
        return $this->type . ':' . $this->id;
    }
}
