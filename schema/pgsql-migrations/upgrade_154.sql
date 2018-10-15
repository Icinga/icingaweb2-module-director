
UPDATE icinga_command_argument
SET argument_format = NULL
WHERE argument_value IS NULL;

UPDATE icinga_command_argument
SET set_if_format = NULL
WHERE set_if IS NULL;

INSERT INTO director_schema_migration
  (schema_version, migration_time)
  VALUES (154, NOW());
