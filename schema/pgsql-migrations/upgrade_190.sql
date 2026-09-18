-- SPDX-FileCopyrightText: 2025 Icinga GmbH <https://icinga.com>
-- SPDX-License-Identifier: GPL-3.0-or-later

ALTER TABLE icinga_dependency ADD COLUMN redundancy_group character varying(255);
ALTER TABLE branched_icinga_dependency ADD COLUMN redundancy_group character varying(255);

INSERT INTO director_schema_migration
  (schema_version, migration_time)
  VALUES (190, NOW());
