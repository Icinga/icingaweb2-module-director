ALTER TYPE enum_sync_rule_update_policy ADD VALUE 'update-only' AFTER 'ignore';

INSERT INTO director_schema_migration
  (schema_version, migration_time)
  VALUES (170, NOW());
