-- SPDX-FileCopyrightText: 2018 Icinga GmbH <https://icinga.com>
-- SPDX-License-Identifier: GPL-3.0-or-later

CREATE TYPE enum_host_service AS ENUM('host', 'service');

ALTER TABLE icinga_notification ADD apply_to enum_host_service NULL DEFAULT NULL;

INSERT INTO director_schema_migration
  (schema_version, migration_time)
  VALUES (96, NOW());
