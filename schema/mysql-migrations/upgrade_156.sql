ALTER TABLE icinga_command
  DROP INDEX object_name,
ADD UNIQUE INDEX object_name (object_name);

INSERT INTO director_schema_migration
  (schema_version, migration_time)
  VALUES (156, NOW());
