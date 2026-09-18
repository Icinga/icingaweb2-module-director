-- SPDX-FileCopyrightText: 2019 Icinga GmbH <https://icinga.com>
-- SPDX-License-Identifier: GPL-3.0-or-later

ALTER TYPE enum_sync_rule_object_type ADD VALUE 'scheduledDowntime';
ALTER TYPE enum_sync_rule_object_type ADD VALUE 'notification';
ALTER TYPE enum_sync_rule_object_type ADD VALUE 'dependency';

INSERT INTO director_schema_migration
  (schema_version, migration_time)
  VALUES (166, NOW());
