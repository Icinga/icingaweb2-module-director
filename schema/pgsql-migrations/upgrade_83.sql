-- SPDX-FileCopyrightText: 2018 Icinga GmbH <https://icinga.com>
-- SPDX-License-Identifier: GPL-3.0-or-later

ALTER TABLE icinga_command ALTER COLUMN command TYPE text;
ALTER TABLE icinga_command ALTER COLUMN command DROP DEFAULT;
ALTER TABLE icinga_command ALTER COLUMN command SET DEFAULT NULL;

INSERT INTO director_schema_migration
  (schema_version, migration_time)
  VALUES (83, NOW());
