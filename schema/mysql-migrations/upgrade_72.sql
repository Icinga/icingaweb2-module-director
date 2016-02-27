
ALTER TABLE director_generated_config
  ADD COLUMN first_activity_checksum VARBINARY(20) NOT NULL AFTER duration;

UPDATE director_generated_config SET first_activity_checksum = last_activity_checksum;

ALTER TABLE director_deployment_log
  ADD COLUMN last_activity_checksum VARBINARY(20) NOT NULL AFTER config_checksum;

UPDATE director_deployment_log l JOIN director_generated_config c ON l.config_checksum = c.checksum SET l.last_activity_checksum = c.last_activity_checksum;

INSERT INTO director_schema_migration
  SET migration_time = NOW(),
      schema_version = 72;
