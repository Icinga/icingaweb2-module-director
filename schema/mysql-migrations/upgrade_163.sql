-- when applying manually make sure to set a sensible timezone for your users
-- otherwise the server / client timezone will be used!

-- SET time_zone = '+02:00';

ALTER TABLE director_activity_log
  MODIFY change_time TIMESTAMP NOT NULL;

ALTER TABLE director_deployment_log
  MODIFY start_time TIMESTAMP NOT NULL,
  MODIFY end_time TIMESTAMP NULL DEFAULT NULL,
  MODIFY abort_time TIMESTAMP NULL DEFAULT NULL;

ALTER TABLE director_schema_migration
  MODIFY migration_time TIMESTAMP NOT NULL;

ALTER TABLE director_job
  MODIFY ts_last_attempt TIMESTAMP NULL DEFAULT NULL,
  MODIFY ts_last_error TIMESTAMP NULL DEFAULT NULL;

ALTER TABLE import_source
  MODIFY last_attempt TIMESTAMP NULL DEFAULT NULL;

ALTER TABLE import_run
  MODIFY start_time TIMESTAMP NOT NULL,
  MODIFY end_time TIMESTAMP NULL DEFAULT NULL;

ALTER TABLE sync_rule
  MODIFY last_attempt TIMESTAMP NULL DEFAULT NULL;

ALTER TABLE sync_run
  MODIFY start_time TIMESTAMP NOT NULL;

INSERT INTO director_schema_migration
  (schema_version, migration_time)
  VALUES (163, NOW());
