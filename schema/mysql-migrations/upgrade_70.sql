ALTER TABLE icinga_timeperiod_range
  DROP FOREIGN KEY icinga_timeperiod_range_timeperiod;

ALTER TABLE icinga_timeperiod_range
  ADD CONSTRAINT icinga_timeperiod_range_timeperiod
    FOREIGN KEY timeperiod (timeperiod_id)
    REFERENCES icinga_timeperiod (id)
    ON DELETE CASCADE
    ON UPDATE CASCADE;

INSERT INTO director_schema_migration
  SET migration_time = NOW(),
      schema_version = 70;
