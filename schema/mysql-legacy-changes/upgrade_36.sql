-- SPDX-FileCopyrightText: 2018 Icinga GmbH <https://icinga.com>
-- SPDX-License-Identifier: GPL-3.0-or-later

ALTER TABLE sync_rule MODIFY COLUMN object_type enum('host', 'host_template', 'service', 'service_template', 'command', 'command_template', 'user', 'user_template', 'hostgroup', 'servicegroup', 'usergroup', 'datalistEntry') NOT NULL;
