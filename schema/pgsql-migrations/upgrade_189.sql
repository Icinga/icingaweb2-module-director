ALTER TABLE director_job ADD COLUMN ts_last_attempt_tmp bigint DEFAULT NULL;
ALTER TABLE director_job ADD COLUMN ts_last_error_tmp bigint DEFAULT NULL;


UPDATE director_job
SET ts_last_attempt_tmp = UNIX_TIMESTAMP(ts_last_attempt) * 1000,
    ts_last_error_tmp = UNIX_TIMESTAMP(ts_last_error) * 1000;

ALTER TABLE director_job
    DROP COLUMN ts_last_attempt,
    DROP COLUMN ts_last_error;

ALTER TABLE director_job RENAME COLUMN ts_last_attempt_tmp TO ts_last_attempt;
ALTER TABLE director_job RENAME COLUMN ts_last_error_tmp TO ts_last_error;

INSERT INTO director_schema_migration
  (schema_version, migration_time)
  VALUES (189, NOW());
