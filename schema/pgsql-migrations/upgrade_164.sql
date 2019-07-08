ALTER TABLE icinga_dependency
  ADD COLUMN parent_host_var character varying(128) DEFAULT NULL;

INSERT INTO director_schema_migration
  (schema_version, migration_time)
  VALUES (164, NOW());
