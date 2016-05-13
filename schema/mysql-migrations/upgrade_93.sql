ALTER TABLE sync_rule
  ADD COLUMN sync_state ENUM(
    'unknown',
    'in-sync',
    'pending-changes',
    'failing'
  ) NOT NULL DEFAULT 'unknown',
  ADD COLUMN last_error_message VARCHAR(255) DEFAULT NULL,
  ADD COLUMN last_attempt DATETIME DEFAULT NULL
;

UPDATE sync_rule r
  JOIN (
    SELECT rule_id, MAX(start_time) AS start_time
      FROM sync_run
      GROUP BY rule_id
  ) lr ON r.id = lr.rule_id
  SET r.last_attempt = lr.start_time;

INSERT INTO director_schema_migration
  (schema_version, migration_time)
  VALUES (93, NOW());
