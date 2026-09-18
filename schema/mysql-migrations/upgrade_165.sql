-- SPDX-FileCopyrightText: 2019 Icinga GmbH <https://icinga.com>
-- SPDX-License-Identifier: GPL-3.0-or-later

ALTER TABLE icinga_host
  MODIFY COLUMN address VARCHAR(255) DEFAULT NULL;

INSERT INTO director_schema_migration
  (schema_version, migration_time)
  VALUES (165, NOW());
