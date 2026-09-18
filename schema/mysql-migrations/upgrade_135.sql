-- SPDX-FileCopyrightText: 2018 Icinga GmbH <https://icinga.com>
-- SPDX-License-Identifier: GPL-3.0-or-later

ALTER TABLE icinga_host
  ADD COLUMN check_timeout SMALLINT UNSIGNED DEFAULT NULL AFTER retry_interval;

ALTER TABLE icinga_service
  ADD COLUMN check_timeout SMALLINT UNSIGNED DEFAULT NULL AFTER retry_interval;

INSERT INTO director_schema_migration
(schema_version, migration_time)
VALUES (135, NOW());
