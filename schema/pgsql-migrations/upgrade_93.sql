CREATE TYPE enum_sync_state AS ENUM(
    'unknown',
    'in-sync',
    'pending-changes',
    'failing'
);

ALTER TABLE sync_rule
  ADD COLUMN sync_state enum_sync_state NOT NULL DEFAULT 'unknown',
  ADD COLUMN last_error_message character varying(255) NULL DEFAULT NULL,
  ADD COLUMN last_attempt timestamp with time zone NULL DEFAULT NULL
;

UPDATE sync_rule
  SET last_attempt = lr.start_time
  FROM (
    SELECT rule_id, MAX(start_time) AS start_time
      FROM sync_run
      GROUP BY rule_id
  ) lr WHERE sync_rule.id = lr.rule_id;

INSERT INTO director_schema_migration
  (schema_version, migration_time)
  VALUES (93, NOW());
