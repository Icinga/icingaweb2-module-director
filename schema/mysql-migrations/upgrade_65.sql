ALTER TABLE icinga_zone
  ADD COLUMN disabled ENUM('y', 'n') NOT NULL DEFAULT 'n' AFTER object_type;

ALTER TABLE icinga_timeperiod
  ADD COLUMN disabled ENUM('y', 'n') NOT NULL DEFAULT 'n' AFTER object_type;

ALTER TABLE icinga_command
  ADD COLUMN disabled ENUM('y', 'n') NOT NULL DEFAULT 'n' AFTER object_type;

ALTER TABLE icinga_apiuser
  ADD COLUMN disabled ENUM('y', 'n') NOT NULL DEFAULT 'n' AFTER object_type;

ALTER TABLE icinga_endpoint
  ADD COLUMN disabled ENUM('y', 'n') NOT NULL DEFAULT 'n' AFTER object_type;

ALTER TABLE icinga_host
  ADD COLUMN disabled ENUM('y', 'n') NOT NULL DEFAULT 'n' AFTER object_type;

ALTER TABLE icinga_service
  ADD COLUMN disabled ENUM('y', 'n') NOT NULL DEFAULT 'n' AFTER object_type;

ALTER TABLE icinga_hostgroup
  ADD COLUMN disabled ENUM('y', 'n') NOT NULL DEFAULT 'n' AFTER object_type;

ALTER TABLE icinga_servicegroup
  ADD COLUMN disabled ENUM('y', 'n') NOT NULL DEFAULT 'n' AFTER object_type;

ALTER TABLE icinga_user
  ADD COLUMN disabled ENUM('y', 'n') NOT NULL DEFAULT 'n' AFTER object_type;

ALTER TABLE icinga_usergroup    
  ADD COLUMN disabled ENUM('y', 'n') NOT NULL DEFAULT 'n' AFTER object_type;

INSERT INTO director_schema_migration
  SET migration_time = NOW(),
      schema_version = 65;

