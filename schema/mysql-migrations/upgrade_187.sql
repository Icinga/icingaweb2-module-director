-- SPDX-FileCopyrightText: 2023 Icinga GmbH <https://icinga.com>
-- SPDX-License-Identifier: GPL-3.0-or-later

ALTER TABLE import_row_modifier ADD COLUMN filter_expression TEXT DEFAULT NULL AFTER priority;

INSERT INTO director_schema_migration
  (schema_version, migration_time)
  VALUES (187, NOW());
