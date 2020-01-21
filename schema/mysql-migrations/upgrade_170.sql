
ALTER TABLE sync_rule
  MODIFY COLUMN update_policy ENUM('merge', 'override', 'ignore', 'update-only') NOT NULL;

INSERT INTO director_schema_migration
  (schema_version, migration_time)
  VALUES (170, NOW());
