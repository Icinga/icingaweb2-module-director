ALTER TABLE director_job
  ADD COLUMN timeperiod_id integer DEFAULT NULL,
  ADD CONSTRAINT director_job_period
    FOREIGN KEY (timeperiod_id)
    REFERENCES icinga_timeperiod (id)
    ON DELETE RESTRICT
    ON UPDATE CASCADE;

INSERT INTO director_schema_migration
  (schema_version, migration_time)
  VALUES (97, NOW());
