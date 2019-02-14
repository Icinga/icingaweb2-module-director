DROP INDEX IF EXISTS notification_var_search_idx;
CREATE INDEX notification_var_search_idx ON icinga_notification_var (varname);

INSERT INTO director_schema_migration
  (schema_version, migration_time)
  VALUES (158, NOW());
