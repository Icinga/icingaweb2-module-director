ALTER TABLE sync_run MODIFY duration_ms INT(10) UNSIGNED DEFAULT NULL;

INSERT INTO director_schema_migration
  SET migration_time = NOW(),
      schema_version = 68;

