ALTER TABLE icinga_service
  ADD COLUMN service_set_id INT(10) UNSIGNED DEFAULT NULL AFTER host_id;

DROP TABLE icinga_service_set_service;

INSERT INTO director_schema_migration
  (schema_version, migration_time)
  VALUES (121, NOW());
