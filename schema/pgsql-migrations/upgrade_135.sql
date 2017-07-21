ALTER TABLE icinga_host
  ADD COLUMN check_timeout smallint DEFAULT NULL;

ALTER TABLE icinga_service
  ADD COLUMN check_timeout smallint DEFAULT NULL;

INSERT INTO director_schema_migration
  (schema_version, migration_time)
  VALUES (135, NOW());
