-- SPDX-FileCopyrightText: 2018 Icinga GmbH <https://icinga.com>
-- SPDX-License-Identifier: GPL-3.0-or-later

ALTER TABLE sync_run MODIFY duration_ms INT(10) UNSIGNED DEFAULT NULL;

INSERT INTO director_schema_migration
  SET migration_time = NOW(),
      schema_version = 68;

