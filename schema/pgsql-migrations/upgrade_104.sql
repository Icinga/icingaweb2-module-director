ALTER TABLE icinga_timeperiod_range
  ADD COLUMN range_key character varying(255) DEFAULT NULL,
  ADD COLUMN range_value character varying(255) DEFAULT NULL;

UPDATE icinga_timeperiod_range
  SET range_key = timeperiod_key,
    range_value = timeperiod_value;

ALTER TABLE icinga_timeperiod_range
  ALTER COLUMN range_key SET NOT NULL,
  ALTER COLUMN range_key DROP DEFAULT,
  ALTER COLUMN range_value SET NOT NULL,
  ALTER COLUMN range_value DROP DEFAULT;

ALTER TABLE icinga_timeperiod_range
  DROP CONSTRAINT icinga_timeperiod_range_pkey,
  ADD PRIMARY KEY (timeperiod_id, range_type, range_key);

ALTER TABLE icinga_timeperiod_range
  DROP COLUMN timeperiod_key,
  DROP COLUMN timeperiod_value;

INSERT INTO director_schema_migration
  (schema_version, migration_time)
  VALUES (104, NOW());
