-- SPDX-FileCopyrightText: 2022 Icinga GmbH <https://icinga.com>
-- SPDX-License-Identifier: GPL-3.0-or-later

CREATE INDEX start_time_idx ON director_deployment_log (start_time);

INSERT INTO director_schema_migration
  (schema_version, migration_time)
  VALUES (179, NOW());
