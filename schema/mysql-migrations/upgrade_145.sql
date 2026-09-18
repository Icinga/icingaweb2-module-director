-- SPDX-FileCopyrightText: 2018 Icinga GmbH <https://icinga.com>
-- SPDX-License-Identifier: GPL-3.0-or-later

ALTER TABLE import_row_modifier
  ADD INDEX source_id (source_id),
  DROP INDEX idx_prio;

INSERT INTO director_schema_migration
  (schema_version, migration_time)
  VALUES (145, NOW());
