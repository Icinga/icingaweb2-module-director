-- SPDX-FileCopyrightText: 2018 Icinga GmbH <https://icinga.com>
-- SPDX-License-Identifier: GPL-3.0-or-later

DROP INDEX IF EXISTS command_object_name;
CREATE UNIQUE INDEX command_object_name ON icinga_command (object_name);

INSERT INTO director_schema_migration
  (schema_version, migration_time)
  VALUES (156, NOW());
