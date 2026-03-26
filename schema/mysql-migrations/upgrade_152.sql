-- SPDX-FileCopyrightText: 2018 Icinga GmbH <https://icinga.com>
-- SPDX-License-Identifier: GPL-3.0-or-later

ALTER TABLE import_source
  ADD UNIQUE INDEX source_name (source_name);

ALTER TABLE sync_rule
  ADD UNIQUE INDEX rule_name (rule_name);

INSERT INTO director_schema_migration
  (schema_version, migration_time)
  VALUES (152, NOW());
