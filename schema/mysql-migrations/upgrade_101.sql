ALTER TABLE icinga_host
  ADD COLUMN api_key VARCHAR(40) DEFAULT NULL AFTER accept_config,
  ADD UNIQUE KEY api_key (api_key);

INSERT INTO director_schema_migration
  (schema_version, migration_time)
  VALUES (101, NOW());
