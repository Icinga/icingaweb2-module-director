-- SPDX-FileCopyrightText: 2018 Icinga GmbH <https://icinga.com>
-- SPDX-License-Identifier: GPL-3.0-or-later

ALTER TABLE icinga_command_argument DROP COLUMN value_format, ADD COLUMN argument_format enum_property_format NOT NULL DEFAULT 'string';

