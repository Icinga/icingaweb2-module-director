UPDATE icinga_command_argument
  SET
    argument_name = '(no key)',
    skip_key = 'y'
  WHERE argument_name is null;

ALTER TABLE icinga_command_argument ALTER COLUMN argument_name SET NOT NULL;

INSERT INTO director_schema_migration
  (schema_version, migration_time)
  VALUES (103, NOW());
