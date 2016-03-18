ALTER TABLE icinga_notification
  MODIFY COLUMN object_type ENUM('object', 'template', 'apply') NOT NULL;

INSERT INTO director_schema_migration
  SET migration_time = NOW(),
      schema_version = 87;
