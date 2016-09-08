ALTER TABLE icinga_service
  ADD COLUMN use_var_overrides ENUM('y', 'n') DEFAULT NULL;

INSERT INTO director_schema_migration
  (schema_version, migration_time)
  VALUES (105, NOW());
