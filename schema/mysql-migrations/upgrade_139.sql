-- SPDX-FileCopyrightText: 2018 Icinga GmbH <https://icinga.com>
-- SPDX-License-Identifier: GPL-3.0-or-later

UPDATE import_row_modifier SET priority = id;

ALTER TABLE import_row_modifier ADD UNIQUE INDEX idx_prio (source_id, priority);

INSERT INTO director_schema_migration
  (schema_version, migration_time)
  VALUES (139, NOW());
