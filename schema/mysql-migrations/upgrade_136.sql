ALTER TABLE director_datalist_entry
  ADD COLUMN allowed_roles VARCHAR(255) DEFAULT NULL;

INSERT INTO director_schema_migration
(schema_version, migration_time)
VALUES (136, NOW());
