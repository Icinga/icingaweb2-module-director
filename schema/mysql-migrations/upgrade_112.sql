ALTER TABLE director_datalist_entry
  MODIFY COLUMN entry_name VARCHAR(255) COLLATE utf8_bin NOT NULL;

INSERT INTO director_schema_migration
  (schema_version, migration_time)
  VALUES (112, NOW());
