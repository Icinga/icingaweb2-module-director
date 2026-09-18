-- SPDX-FileCopyrightText: 2018 Icinga GmbH <https://icinga.com>
-- SPDX-License-Identifier: GPL-3.0-or-later

ALTER TABLE icinga_command
  ADD COLUMN is_string enum ('y', 'n') NULL;

INSERT INTO director_schema_migration
  (schema_version, migration_time)
  VALUES (160, NOW());
