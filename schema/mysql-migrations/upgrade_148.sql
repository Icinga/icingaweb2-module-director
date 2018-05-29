ALTER TABLE import_source
  MODIFY provider_class VARCHAR(128) NOT NULL;

ALTER TABLE import_row_modifier
  MODIFY provider_class VARCHAR(128) NOT NULL;


INSERT INTO director_schema_migration
  (schema_version, migration_time)
  VALUES (148, NOW());
