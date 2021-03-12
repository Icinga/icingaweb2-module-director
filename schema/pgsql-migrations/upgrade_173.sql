ALTER TABLE sync_rule
  ALTER COLUMN purge_action SET DEFAULT NULL;

INSERT INTO director_schema_migration
  (schema_version, migration_time)
  VALUES (173, NOW());
