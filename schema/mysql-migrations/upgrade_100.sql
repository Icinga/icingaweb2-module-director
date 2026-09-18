-- SPDX-FileCopyrightText: 2018 Icinga GmbH <https://icinga.com>
-- SPDX-License-Identifier: GPL-3.0-or-later

ALTER TABLE import_row_modifier
  ADD COLUMN target_property VARCHAR(255) DEFAULT NULL AFTER property_name;

INSERT INTO director_schema_migration
  (schema_version, migration_time)
  VALUES (100, NOW());
