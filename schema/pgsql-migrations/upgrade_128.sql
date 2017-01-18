CREATE INDEX activity_log_author ON director_activity_log (author);

INSERT INTO director_schema_migration
  (schema_version, migration_time)
  VALUES (128, NOW());
