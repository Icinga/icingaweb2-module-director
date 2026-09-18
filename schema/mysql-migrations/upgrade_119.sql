-- SPDX-FileCopyrightText: 2018 Icinga GmbH <https://icinga.com>
-- SPDX-License-Identifier: GPL-3.0-or-later

ALTER TABLE icinga_service
  ADD COLUMN apply_for VARCHAR(255) DEFAULT NULL AFTER use_agent;

INSERT INTO director_schema_migration
  (schema_version, migration_time)
  VALUES (119, NOW());
