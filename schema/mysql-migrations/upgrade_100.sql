ALTER TABLE import_row_modifier
  ADD COLUMN target_property VARCHAR(255) DEFAULT NULL AFTER property_name;

INSERT INTO director_schema_migration
  (schema_version, migration_time)
  VALUES (100, NOW());
