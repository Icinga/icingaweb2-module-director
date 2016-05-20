CREATE OR REPLACE FUNCTION unix_timestamp(timestamp with time zone) RETURNS bigint AS '
        SELECT EXTRACT(EPOCH FROM $1)::bigint AS result
' LANGUAGE sql;

INSERT INTO director_schema_migration
  (schema_version, migration_time)
  VALUES (98, NOW());
