DELETE FROM director_datalist_entry WHERE entry_name IS NULL;
ALTER TABLE director_datalist_entry
  MODIFY entry_name VARCHAR(255) NOT NULL;

DELETE FROM icinga_command_var WHERE varname IS NULL;
ALTER TABLE icinga_command_var
  MODIFY varname VARCHAR(255) NOT NULL;

DELETE FROM icinga_host_var WHERE varname IS NULL;
ALTER TABLE icinga_host_var
  MODIFY varname VARCHAR(255) NOT NULL;

DELETE FROM icinga_service_var WHERE varname IS NULL;
ALTER TABLE icinga_service_var
  MODIFY varname VARCHAR(255) NOT NULL;

DELETE FROM icinga_user_var WHERE varname IS NULL;
ALTER TABLE icinga_user_var
  MODIFY varname VARCHAR(255) NOT NULL;

DELETE FROM icinga_notification_var WHERE varname IS NULL;
ALTER TABLE icinga_notification_var
  MODIFY varname VARCHAR(255) NOT NULL;

INSERT INTO director_schema_migration
  (schema_version, migration_time)
  VALUES (92, NOW());
