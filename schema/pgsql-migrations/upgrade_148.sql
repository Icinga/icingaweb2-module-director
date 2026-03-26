-- SPDX-FileCopyrightText: 2018 Icinga GmbH <https://icinga.com>
-- SPDX-License-Identifier: GPL-3.0-or-later

ALTER TABLE import_source
  ALTER COLUMN provider_class TYPE character varying(128);

ALTER TABLE import_row_modifier
  ALTER COLUMN provider_class TYPE character varying(128);


INSERT INTO director_schema_migration
  (schema_version, migration_time)
  VALUES (148, NOW());
