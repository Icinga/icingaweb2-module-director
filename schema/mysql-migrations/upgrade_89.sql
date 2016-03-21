ALTER TABLE icinga_command_argument
  ADD required ENUM('y', 'n') DEFAULT NULL AFTER repeat_key;

INSERT INTO director_schema_migration
  SET migration_time = NOW(),
      schema_version = 89;
