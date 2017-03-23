ALTER TABLE icinga_hostgroup
  MODIFY object_type enum('object', 'template', 'external_object') NOT NULL;

INSERT INTO director_schema_migration
(schema_version, migration_time)
VALUES (130, NOW());
