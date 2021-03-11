CREATE TYPE enum_sync_rule_purge_action AS ENUM('delete', 'disable');

ALTER TABLE sync_rule
  ADD COLUMN purge_action enum_sync_rule_purge_action NULL DEFAULT NULL;

UPDATE sync_rule SET purge_action = 'delete';

ALTER TABLE sync_rule
  ALTER COLUMN purge_action SET NOT NULL;

INSERT INTO director_schema_migration
  (schema_version, migration_time)
  VALUES (172, NOW());
