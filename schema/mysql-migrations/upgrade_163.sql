-- when applying manually make sure to set a sensible timezone for your users
-- otherwise the server / client timezone will be used!

-- SET time_zone = '+02:00';

SET sql_mode = 'STRICT_ALL_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION,PIPES_AS_CONCAT,ANSI_QUOTES,ERROR_FOR_DIVISION_BY_ZERO';

ALTER TABLE director_activity_log
  MODIFY change_time TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP;

ALTER TABLE director_deployment_log
  MODIFY start_time TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  MODIFY end_time TIMESTAMP NULL DEFAULT NULL,
  MODIFY abort_time TIMESTAMP NULL DEFAULT NULL;

ALTER TABLE director_schema_migration
  MODIFY migration_time TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP;

ALTER TABLE director_job
  MODIFY ts_last_attempt TIMESTAMP NULL DEFAULT NULL,
  MODIFY ts_last_error TIMESTAMP NULL DEFAULT NULL;

ALTER TABLE import_source
  MODIFY last_attempt TIMESTAMP NULL DEFAULT NULL;

ALTER TABLE import_run
  MODIFY start_time TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  MODIFY end_time TIMESTAMP NULL DEFAULT NULL;

ALTER TABLE sync_rule
  MODIFY last_attempt TIMESTAMP NULL DEFAULT NULL;

ALTER TABLE sync_run
  MODIFY start_time TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP;

INSERT INTO director_schema_migration
  (schema_version, migration_time)
  VALUES (163, NOW());
