-- SPDX-FileCopyrightText: 2021 Icinga GmbH <https://icinga.com>
-- SPDX-License-Identifier: GPL-3.0-or-later

ALTER TABLE icinga_host ADD COLUMN custom_endpoint_name character varying(255) DEFAULT NULL;
ALTER TABLE branched_icinga_host ADD COLUMN custom_endpoint_name character varying(255) DEFAULT NULL;

INSERT INTO director_schema_migration
  (schema_version, migration_time)
  VALUES ('176', NOW());
