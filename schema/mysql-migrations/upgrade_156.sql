-- SPDX-FileCopyrightText: 2018 Icinga GmbH <https://icinga.com>
-- SPDX-License-Identifier: GPL-3.0-or-later

ALTER TABLE icinga_command
  DROP INDEX object_name,
ADD UNIQUE INDEX object_name (object_name);

INSERT INTO director_schema_migration
  (schema_version, migration_time)
  VALUES (156, NOW());
