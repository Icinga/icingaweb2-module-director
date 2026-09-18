-- SPDX-FileCopyrightText: 2019 Icinga GmbH <https://icinga.com>
-- SPDX-License-Identifier: GPL-3.0-or-later

ALTER TABLE icinga_host
  ALTER COLUMN address TYPE character varying(255);

INSERT INTO director_schema_migration
  (schema_version, migration_time)
  VALUES (165, NOW());
