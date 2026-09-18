-- SPDX-FileCopyrightText: 2020 Icinga GmbH <https://icinga.com>
-- SPDX-License-Identifier: GPL-3.0-or-later

ALTER TYPE enum_sync_rule_update_policy ADD VALUE 'update-only' AFTER 'ignore';

INSERT INTO director_schema_migration
  (schema_version, migration_time)
  VALUES (170, NOW());
