-- SPDX-FileCopyrightText: 2018 Icinga GmbH <https://icinga.com>
-- SPDX-License-Identifier: GPL-3.0-or-later

UPDATE icinga_service_set
  SET object_type = 'template'
  WHERE object_type = 'object' AND host_id IS NULL;

INSERT INTO director_schema_migration
  (schema_version, migration_time)
  VALUES (141, NOW());
