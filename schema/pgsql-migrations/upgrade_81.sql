-- SPDX-FileCopyrightText: 2018 Icinga GmbH <https://icinga.com>
-- SPDX-License-Identifier: GPL-3.0-or-later

ALTER TABLE import_run ALTER COLUMN end_time DROP NOT NULL;

INSERT INTO director_schema_migration
  (schema_version, migration_time)
  VALUES (81, NOW());
