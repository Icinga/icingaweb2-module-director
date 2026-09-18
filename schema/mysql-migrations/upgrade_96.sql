-- SPDX-FileCopyrightText: 2018 Icinga GmbH <https://icinga.com>
-- SPDX-License-Identifier: GPL-3.0-or-later

ALTER TABLE icinga_notification ADD apply_to ENUM('host', 'service') DEFAULT NULL AFTER disabled;

INSERT INTO director_schema_migration
  (schema_version, migration_time)
  VALUES (96, NOW());
