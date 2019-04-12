CREATE TABLE icinga_scheduled_downtime
  ADD COLUMN with_services enum_boolean NULL DEFAULT NULL;

INSERT INTO director_schema_migration
  (schema_version, migration_time)
  VALUES (162, NOW());
