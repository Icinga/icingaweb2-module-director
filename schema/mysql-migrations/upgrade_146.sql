ALTER TABLE icinga_host
  DROP COLUMN flapping_threshold,
  ADD COLUMN flapping_threshold_high SMALLINT UNSIGNED DEFAULT NULL,
  ADD COLUMN flapping_threshold_low SMALLINT UNSIGNED DEFAULT NULL;

ALTER TABLE icinga_service
  DROP COLUMN flapping_threshold,
  ADD COLUMN flapping_threshold_high SMALLINT UNSIGNED DEFAULT NULL,
  ADD COLUMN flapping_threshold_low SMALLINT UNSIGNED DEFAULT NULL;


INSERT INTO director_schema_migration
  (schema_version, migration_time)
  VALUES (146, NOW());
