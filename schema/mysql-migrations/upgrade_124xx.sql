ALTER TABLE icinga_command_field
  ADD COLUMN var_filter TEXT DEFAULT NULL;

ALTER TABLE icinga_host_field
  ADD COLUMN var_filter TEXT DEFAULT NULL;

ALTER TABLE icinga_notification_field
  ADD COLUMN var_filter TEXT DEFAULT NULL;

ALTER TABLE icinga_service_field
  ADD COLUMN var_filter TEXT DEFAULT NULL;

ALTER TABLE icinga_user_field
  ADD COLUMN var_filter TEXT DEFAULT NULL;

INSERT INTO director_schema_migration
  (schema_version, migration_time)
  VALUES (124, NOW());
