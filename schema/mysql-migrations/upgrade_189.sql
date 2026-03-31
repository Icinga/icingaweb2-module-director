ALTER TABLE director_job ADD COLUMN ts_last_attempt_tmp BIGINT(20) DEFAULT NULL;
ALTER TABLE director_job ADD COLUMN ts_last_error_tmp BIGINT(20) DEFAULT NULL;


UPDATE director_job
SET ts_last_attempt_tmp = UNIX_TIMESTAMP(ts_last_attempt) * 1000,
    ts_last_error_tmp = UNIX_TIMESTAMP(ts_last_error) * 1000;

ALTER TABLE director_job
    DROP COLUMN ts_last_attempt,
    DROP COLUMN ts_last_error,
    CHANGE ts_last_attempt_tmp ts_last_attempt BIGINT(20) DEFAULT NULL,
    CHANGE ts_last_error_tmp ts_last_error BIGINT(20) DEFAULT NULL;

INSERT INTO director_schema_migration
(schema_version, migration_time)
VALUES (189, NOW());
