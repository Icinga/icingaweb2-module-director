ALTER TABLE icinga_host
  ALTER COLUMN address TYPE character varying(255);

INSERT INTO director_schema_migration
  (schema_version, migration_time)
  VALUES (165, NOW());
