-- SPDX-FileCopyrightText: 2022 Icinga GmbH <https://icinga.com>
-- SPDX-License-Identifier: GPL-3.0-or-later

ALTER TABLE director_deployment_log ADD INDEX (start_time);

INSERT INTO director_schema_migration
  (schema_version, migration_time)
  VALUES ('179', NOW());
