-- SPDX-FileCopyrightText: 2018 Icinga GmbH <https://icinga.com>
-- SPDX-License-Identifier: GPL-3.0-or-later

ALTER TABLE icinga_host
  DROP COLUMN flapping_threshold,
  ADD COLUMN flapping_threshold_high smallint DEFAULT NULL,
  ADD COLUMN flapping_threshold_low smallint DEFAULT NULL;

ALTER TABLE icinga_service
  DROP COLUMN flapping_threshold,
  ADD COLUMN flapping_threshold_high smallint DEFAULT NULL,
  ADD COLUMN flapping_threshold_low smallint DEFAULT NULL;


INSERT INTO director_schema_migration
  (schema_version, migration_time)
  VALUES (146, NOW());
