-- SPDX-FileCopyrightText: 2018 Icinga GmbH <https://icinga.com>
-- SPDX-License-Identifier: GPL-3.0-or-later

ALTER TABLE icinga_service_assignment ADD assign_type ENUM('assign', 'ignore') NOT NULL DEFAULT 'assign';

INSERT INTO director_schema_migration
  (schema_version, migration_time)
  VALUES (90, NOW());
