UPDATE icinga_command_argument
  SET
    argument_name = '(no key)',
    skip_key = 'y'
  WHERE argument_name IS NULL;

ALTER TABLE icinga_command_argument
  MODIFY argument_name VARCHAR(64) COLLATE utf8_bin NOT NULL COMMENT '-x, --host';

INSERT INTO director_schema_migration
  (schema_version, migration_time)
  VALUES (103, NOW());
