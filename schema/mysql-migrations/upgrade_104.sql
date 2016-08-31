ALTER TABLE icinga_timeperiod_range
  ADD COLUMN range_key VARCHAR(255) NOT NULL COMMENT 'monday, ...',
  ADD COLUMN range_value VARCHAR(255) NOT NULL COMMENT '00:00-24:00, ...';

UPDATE icinga_timeperiod_range
  SET range_key = timeperiod_key,
    range_value = timeperiod_value;

ALTER TABLE icinga_timeperiod_range
  DROP PRIMARY KEY,
  ADD PRIMARY KEY (timeperiod_id, range_type, range_key);

ALTER TABLE icinga_timeperiod_range
  DROP COLUMN timeperiod_key,
  DROP COLUMN timeperiod_value;

INSERT INTO director_schema_migration
  (schema_version, migration_time)
  VALUES (104, NOW());
