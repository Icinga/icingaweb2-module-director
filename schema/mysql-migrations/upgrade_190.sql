ALTER TABLE icinga_dependency ADD COLUMN redundancy_group VARCHAR(255) DEFAULT NULL AFTER parent_service_by_name;
ALTER TABLE branched_icinga_dependency ADD COLUMN redundancy_group VARCHAR(255) DEFAULT NULL AFTER parent_service_by_name;

INSERT INTO director_schema_migration
  (schema_version, migration_time)
  VALUES (190, NOW());
