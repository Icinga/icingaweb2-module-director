CREATE DOMAIN d_smallint AS integer CHECK (VALUE >= 0) CHECK (VALUE < 65536);

ALTER TABLE icinga_endpoint ALTER COLUMN port TYPE d_smallint;


INSERT INTO director_schema_migration
  (schema_version, migration_time)
  VALUES (169, NOW());
