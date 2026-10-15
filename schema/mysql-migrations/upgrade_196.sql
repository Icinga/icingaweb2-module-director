ALTER TABLE import_run
  MODIFY start_time TIMESTAMP(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6);

ALTER TABLE sync_run
  MODIFY start_time TIMESTAMP(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6);

INSERT INTO director_schema_migration
  (schema_version, migration_time)
VALUES
  (196, NOW());
