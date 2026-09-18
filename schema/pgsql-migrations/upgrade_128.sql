-- SPDX-FileCopyrightText: 2018 Icinga GmbH <https://icinga.com>
-- SPDX-License-Identifier: GPL-3.0-or-later

CREATE INDEX activity_log_author ON director_activity_log (author);

INSERT INTO director_schema_migration
  (schema_version, migration_time)
  VALUES (128, NOW());
