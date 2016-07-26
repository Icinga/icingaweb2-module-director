ALTER TABLE icinga_host
  ADD COLUMN api_key character varying(40) DEFAULT NULL;

CREATE UNIQUE INDEX host_api_key ON icinga_host (api_key);

INSERT INTO director_schema_migration
  (schema_version, migration_time)
  VALUES (101, NOW());

