ALTER TABLE sync_rule
  ADD COLUMN purge_action ENUM('delete', 'disable') NULL DEFAULT NULL AFTER purge_existing;

UPDATE sync_rule SET purge_action = 'delete';

ALTER TABLE sync_rule
  MODIFY COLUMN purge_action ENUM('delete', 'disable') DEFAULT NULL;

INSERT INTO director_schema_migration
  (schema_version, migration_time)
  VALUES (172, NOW());
