-- SPDX-FileCopyrightText: 2018 Icinga GmbH <https://icinga.com>
-- SPDX-License-Identifier: GPL-3.0-or-later

ALTER TABLE director_activity_log
  ADD INDEX search_author (author);

INSERT INTO director_schema_migration
  (schema_version, migration_time)
  VALUES (128, NOW());
