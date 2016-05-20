ALTER TABLE import_source
  ADD COLUMN import_state enum_sync_state NOT NULL DEFAULT 'unknown',
  ADD COLUMN last_error_message character varying(255) NULL DEFAULT NULL,
  ADD COLUMN last_attempt timestamp with time zone NULL DEFAULT NULL
;


UPDATE import_source
  SET last_attempt = ir.start_time
  FROM (
    SELECT source_id, MAX(start_time) AS start_time
      FROM import_run
      GROUP BY source_id
  ) ir WHERE import_source.id = ir.source_id;



INSERT INTO director_schema_migration
  (schema_version, migration_time)
  VALUES (95, NOW());
