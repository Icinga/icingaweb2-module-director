ALTER TABLE sync_property
  ALTER COLUMN merge_policy DROP NOT NULL;

ALTER TABLE sync_run
  ALTER COLUMN rule_id DROP NOT NULL;

INSERT INTO director_schema_migration
  (schema_version, migration_time)
  VALUES (106, NOW());
