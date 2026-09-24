ALTER TABLE icinga_notification
  MODIFY COLUMN object_type ENUM('object', 'template', 'apply', 'external_object') NOT NULL;

INSERT INTO director_schema_migration
(schema_version, migration_time)
VALUES (194, NOW());
