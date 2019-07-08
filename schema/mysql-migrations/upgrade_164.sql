SET sql_mode = 'STRICT_ALL_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION,PIPES_AS_CONCAT,ANSI_QUOTES,ERROR_FOR_DIVISION_BY_ZERO';

ALTER TABLE icinga_dependency
  ADD COLUMN parent_host_var VARCHAR(128) DEFAULT NULL AFTER parent_host_id;

INSERT INTO director_schema_migration
  (schema_version, migration_time)
  VALUES (164, NOW());
