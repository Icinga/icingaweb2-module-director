-- SPDX-FileCopyrightText: 2018 Icinga GmbH <https://icinga.com>
-- SPDX-License-Identifier: GPL-3.0-or-later

ALTER TABLE icinga_hostgroup
  MODIFY object_type enum('object', 'template', 'external_object') NOT NULL;

INSERT INTO director_schema_migration
(schema_version, migration_time)
VALUES (130, NOW());
