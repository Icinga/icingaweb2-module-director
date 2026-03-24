-- SPDX-FileCopyrightText: 2018 Icinga GmbH <https://icinga.com>
-- SPDX-License-Identifier: GPL-3.0-or-later

ALTER TABLE icinga_command_argument DROP COLUMN value_format, ADD COLUMN argument_format ENUM('string', 'expression', 'json') NOT NULL DEFAULT 'string' AFTER argument_value;
ALTER TABLE icinga_command_argument DEFAULT COLLATE utf8_bin;
ALTER TABLE icinga_command_argument MODIFY COLUMN argument_name VARCHAR(64) DEFAULT NULL COLLATE utf8_bin;



