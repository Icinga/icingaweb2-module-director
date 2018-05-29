ALTER TABLE import_source
  ALTER COLUMN provider_class TYPE character varying(128);

ALTER TABLE import_row_modifier
  ALTER COLUMN provider_class TYPE character varying(128);


INSERT INTO director_schema_migration
  (schema_version, migration_time)
  VALUES (148, NOW());
