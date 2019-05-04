ALTER TABLE icinga_command
  ADD COLUMN is_string enum ('y', 'n') NULL;

INSERT INTO director_schema_migration
  (schema_version, migration_time)
  VALUES (160, NOW());
