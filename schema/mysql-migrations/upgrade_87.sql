-- SPDX-FileCopyrightText: 2018 Icinga GmbH <https://icinga.com>
-- SPDX-License-Identifier: GPL-3.0-or-later

ALTER TABLE icinga_notification
  MODIFY COLUMN object_type ENUM('object', 'template', 'apply') NOT NULL;

INSERT INTO director_schema_migration
  SET migration_time = NOW(),
      schema_version = 87;
