ALTER TABLE director_datafield
  MODIFY COLUMN varname VARCHAR(64) CHARACTER SET utf8 COLLATE utf8_bin;

INSERT INTO director_schema_migration
  (schema_version, migration_time)
  VALUES (129, NOW());
