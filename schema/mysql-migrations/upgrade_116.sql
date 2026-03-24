-- SPDX-FileCopyrightText: 2018 Icinga GmbH <https://icinga.com>
-- SPDX-License-Identifier: GPL-3.0-or-later

ALTER TABLE sync_rule MODIFY object_type enum(
  'host',
  'service',
  'command',
  'user',
  'hostgroup',
  'servicegroup',
  'usergroup',
  'datalistEntry',
  'endpoint',
  'zone',
  'timePeriod',
  'serviceSet'
) NOT NULL;

INSERT INTO director_schema_migration
  (schema_version, migration_time)
  VALUES (116, NOW());
