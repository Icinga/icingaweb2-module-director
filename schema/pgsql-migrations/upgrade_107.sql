-- SPDX-FileCopyrightText: 2018 Icinga GmbH <https://icinga.com>
-- SPDX-License-Identifier: GPL-3.0-or-later

ALTER TABLE import_source
    ALTER COLUMN last_error_message TYPE TEXT;

ALTER TABLE sync_rule
  ALTER COLUMN last_error_message TYPE TEXT;

INSERT INTO director_schema_migration
  (schema_version, migration_time)
  VALUES (107, NOW());
