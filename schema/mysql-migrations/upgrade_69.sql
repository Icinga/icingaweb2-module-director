ALTER TABLE sync_run
  DROP COLUMN first_related_activity,
  ADD COLUMN last_former_activity VARBINARY(20) DEFAULT NULL AFTER objects_modified;

INSERT INTO director_schema_migration
  SET migration_time = NOW(),
      schema_version = 69;


