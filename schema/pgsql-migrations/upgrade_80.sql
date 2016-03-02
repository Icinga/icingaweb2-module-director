ALTER TABLE icinga_timeperiod_range
  DROP CONSTRAINT icinga_timeperiod_range_timeperiod,
  ADD CONSTRAINT icinga_timeperiod_range_timeperiod
  FOREIGN KEY (timeperiod_id)
    REFERENCES icinga_timeperiod (id)
    ON DELETE CASCADE
    ON UPDATE CASCADE;

INSERT INTO director_schema_migration
  (schema_version, migration_time)
  VALUES (80, NOW());
