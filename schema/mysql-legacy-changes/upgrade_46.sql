-- SPDX-FileCopyrightText: 2018 Icinga GmbH <https://icinga.com>
-- SPDX-License-Identifier: GPL-3.0-or-later

ALTER TABLE icinga_command_argument MODIFY argument_name VARCHAR(64) COLLATE utf8_bin DEFAULT NULL COMMENT '-x, --host';

