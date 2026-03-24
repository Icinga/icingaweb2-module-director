-- SPDX-FileCopyrightText: 2018 Icinga GmbH <https://icinga.com>
-- SPDX-License-Identifier: GPL-3.0-or-later

INSERT INTO director_schema_migration
  (schema_version, migration_time)
  VALUES (171, NOW());
