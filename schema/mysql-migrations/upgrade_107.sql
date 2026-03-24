-- SPDX-FileCopyrightText: 2018 Icinga GmbH <https://icinga.com>
-- SPDX-License-Identifier: GPL-3.0-or-later

ALTER TABLE director_job
  MODIFY last_error_message TEXT DEFAULT NULL;

ALTER TABLE sync_rule
  MODIFY last_error_message TEXT DEFAULT NULL;

INSERT INTO director_schema_migration
  (schema_version, migration_time)
  VALUES (107, NOW());
