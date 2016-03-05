ALTER TABLE import_run ALTER COLUMN end_time DROP NOT NULL;

INSERT INTO director_schema_migration
  (schema_version, migration_time)
  VALUES (81, NOW());
