-- SPDX-FileCopyrightText: 2021 Icinga GmbH <https://icinga.com>
-- SPDX-License-Identifier: GPL-3.0-or-later

ALTER TABLE sync_rule
  MODIFY COLUMN purge_action ENUM('delete', 'disable') NULL DEFAULT NULL;

INSERT INTO director_schema_migration
  (schema_version, migration_time)
  VALUES (173, NOW());
