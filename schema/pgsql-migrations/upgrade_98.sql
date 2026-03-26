-- SPDX-FileCopyrightText: 2018 Icinga GmbH <https://icinga.com>
-- SPDX-License-Identifier: GPL-3.0-or-later

CREATE OR REPLACE FUNCTION unix_timestamp(timestamp with time zone) RETURNS bigint AS '
        SELECT EXTRACT(EPOCH FROM $1)::bigint AS result
' LANGUAGE sql;

INSERT INTO director_schema_migration
  (schema_version, migration_time)
  VALUES (98, NOW());
