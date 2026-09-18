-- SPDX-FileCopyrightText: 2018 Icinga GmbH <https://icinga.com>
-- SPDX-License-Identifier: GPL-3.0-or-later

ALTER TYPE enum_sync_rule_object_type ADD VALUE 'timePeriod';
ALTER TYPE enum_sync_rule_object_type ADD VALUE 'serviceSet';

INSERT INTO director_schema_migration
  (schema_version, migration_time)
  VALUES (116, NOW());
