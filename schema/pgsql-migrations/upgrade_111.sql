ALTER TABLE import_run
 DROP CONSTRAINT import_run_source,
 ADD CONSTRAINT import_run_source
  FOREIGN KEY (source_id)
  REFERENCES import_source (id)
  ON DELETE CASCADE
  ON UPDATE RESTRICT;

INSERT INTO director_schema_migration
  (schema_version, migration_time)
  VALUES (111, NOW());
