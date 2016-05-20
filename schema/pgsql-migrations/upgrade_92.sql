DELETE FROM director_datalist_entry WHERE entry_name IS NULL;
ALTER TABLE director_datalist_entry ALTER COLUMN entry_name DROP DEFAULT;
ALTER TABLE director_datalist_entry ALTER COLUMN entry_name SET NOT NULL;

DELETE FROM icinga_command_var WHERE varname IS NULL;
ALTER TABLE icinga_command_var ALTER COLUMN varname DROP DEFAULT;
ALTER TABLE icinga_command_var ALTER COLUMN varname SET NOT NULL;

DELETE FROM icinga_host_var WHERE varname IS NULL;
ALTER TABLE icinga_host_var ALTER COLUMN varname DROP DEFAULT;
ALTER TABLE icinga_host_var ALTER COLUMN varname SET NOT NULL;

DELETE FROM icinga_service_var WHERE varname IS NULL;
ALTER TABLE icinga_service_var ALTER COLUMN varname DROP DEFAULT;
ALTER TABLE icinga_service_var ALTER COLUMN varname SET NOT NULL;

DELETE FROM icinga_user_var WHERE varname IS NULL;
ALTER TABLE icinga_user_var ALTER COLUMN varname DROP DEFAULT;
ALTER TABLE icinga_user_var ALTER COLUMN varname SET NOT NULL;

DELETE FROM icinga_notification_var WHERE varname IS NULL;
ALTER TABLE icinga_notification_var ALTER COLUMN varname DROP DEFAULT;
ALTER TABLE icinga_notification_var ALTER COLUMN varname SET NOT NULL;

INSERT INTO director_schema_migration
  (schema_version, migration_time)
  VALUES (92, NOW());
