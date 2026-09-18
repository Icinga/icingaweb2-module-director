-- SPDX-FileCopyrightText: 2018 Icinga GmbH <https://icinga.com>
-- SPDX-License-Identifier: GPL-3.0-or-later

ALTER TABLE icinga_usergroup DROP COLUMN zone_id;

INSERT INTO director_schema_migration
  (schema_version, migration_time)
  VALUES (84, NOW());
