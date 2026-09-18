-- SPDX-FileCopyrightText: 2018 Icinga GmbH <https://icinga.com>
-- SPDX-License-Identifier: GPL-3.0-or-later

ALTER TABLE director_datalist_entry
  MODIFY COLUMN entry_name VARCHAR(255) COLLATE utf8_bin NOT NULL;

INSERT INTO director_schema_migration
  (schema_version, migration_time)
  VALUES (112, NOW());
