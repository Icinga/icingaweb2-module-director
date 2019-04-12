ALTER TABLE icinga_scheduled_downtime
  ADD COLUMN with_services ENUM('y', 'n') NULL DEFAULT NULL;

INSERT INTO director_schema_migration
  (schema_version, migration_time)
  VALUES (162, NOW());
