DROP INDEX command_object_name;
CREATE UNIQUE INDEX command_object_name ON icinga_command (object_name);

INSERT INTO director_schema_migration
  (schema_version, migration_time)
  VALUES (156, NOW());
