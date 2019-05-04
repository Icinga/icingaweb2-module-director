ALTER TABLE icinga_command
  ADD COLUMN is_string enum_boolean NULL;

INSERT INTO director_schema_migration
  (schema_version, migration_time)
  VALUES (160, NOW());
