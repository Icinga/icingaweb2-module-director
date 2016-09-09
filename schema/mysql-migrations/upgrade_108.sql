ALTER TABLE icinga_command_var
  MODIFY COLUMN varname VARCHAR(255) NOT NULL COLLATE utf8_bin;

ALTER TABLE icinga_host_var
  MODIFY COLUMN varname VARCHAR(255) NOT NULL COLLATE utf8_bin;

ALTER TABLE icinga_service_var
  MODIFY COLUMN varname VARCHAR(255) NOT NULL COLLATE utf8_bin;

ALTER TABLE icinga_user_var
  MODIFY COLUMN varname VARCHAR(255) NOT NULL COLLATE utf8_bin;

ALTER TABLE icinga_notification_var
  MODIFY COLUMN varname VARCHAR(255) NOT NULL COLLATE utf8_bin;

INSERT INTO director_schema_migration
  (schema_version, migration_time)
  VALUES (108, NOW());
