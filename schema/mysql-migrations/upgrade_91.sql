ALTER TABLE icinga_notification_assignment ADD assign_type ENUM('assign', 'ignore') NOT NULL DEFAULT 'assign';

INSERT INTO director_schema_migration
  (schema_version, migration_time)
  VALUES (91, NOW());
