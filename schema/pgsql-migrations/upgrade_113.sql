COMMENT ON COLUMN icinga_timeperiod_range.range_key IS 'monday, ...';
COMMENT ON COLUMN icinga_timeperiod_range.range_value IS '00:00-24:00, ...';

INSERT INTO director_schema_migration
  (schema_version, migration_time)
  VALUES (113, NOW());
