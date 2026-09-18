-- SPDX-FileCopyrightText: 2019 Icinga GmbH <https://icinga.com>
-- SPDX-License-Identifier: GPL-3.0-or-later

ALTER TABLE director_generated_file
  MODIFY COLUMN content LONGTEXT NOT NULL;

INSERT INTO director_schema_migration
  (schema_version, migration_time)
  VALUES (159, NOW());
