ALTER TABLE icinga_notification ADD apply_to ENUM('host', 'service') DEFAULT NULL AFTER disabled;

INSERT INTO director_schema_migration
  (schema_version, migration_time)
  VALUES (96, NOW());
