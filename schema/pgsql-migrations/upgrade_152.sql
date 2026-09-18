-- SPDX-FileCopyrightText: 2018 Icinga GmbH <https://icinga.com>
-- SPDX-License-Identifier: GPL-3.0-or-later

CREATE UNIQUE INDEX import_source_name ON import_source (source_name);

CREATE UNIQUE INDEX sync_rule_name ON sync_rule (rule_name);

INSERT INTO director_schema_migration
  (schema_version, migration_time)
VALUES (152, NOW());
