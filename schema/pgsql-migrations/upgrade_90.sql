CREATE TYPE enum_assign_type AS ENUM('assign', 'ignore');
ALTER TABLE icinga_service_assignment ADD assign_type enum_assign_type NOT NULL DEFAULT 'assign';

INSERT INTO director_schema_migration
  (schema_version, migration_time)
  VALUES (90, NOW());
