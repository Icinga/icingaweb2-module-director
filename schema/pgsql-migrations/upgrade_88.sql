ALTER TABLE director_generated_config_file
  ALTER COLUMN file_path TYPE character varying(128);

INSERT INTO director_schema_migration
  (schema_version, migration_time)
  VALUES (88, NOW());
