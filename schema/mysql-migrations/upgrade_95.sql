ALTER TABLE import_source
  ADD COLUMN import_state ENUM(
    'unknown',
    'in-sync',
    'pending-changes',
    'failing'
  ) NOT NULL DEFAULT 'unknown',
  ADD COLUMN last_error_message TEXT DEFAULT NULL,
  ADD COLUMN last_attempt DATETIME DEFAULT NULL
;

UPDATE import_source s
  JOIN (
    SELECT source_id, MAX(start_time) AS start_time
      FROM import_run
      GROUP BY source_id
  ) ir ON s.id = ir.source_id
  SET s.last_attempt = ir.start_time;

INSERT INTO director_schema_migration
  (schema_version, migration_time)
  VALUES (95, NOW());
