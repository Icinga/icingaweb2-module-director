ALTER TABLE import_row_modifier
  ADD COLUMN target_property character varying(255) DEFAULT NULL;

INSERT INTO director_schema_migration
  (schema_version, migration_time)
  VALUES (100, NOW());
