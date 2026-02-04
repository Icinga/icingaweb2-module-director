CREATE TABLE director_property (
  uuid binary(16) NOT NULL,
  parent_uuid binary(16) DEFAULT NULL,
  key_name varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  label varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  value_type enum(
          'string',
          'number',
          'bool',
          'fixed-array',
          'dynamic-array',
          'fixed-dictionary',
          'dynamic-dictionary',
          'datalist-strict',
          'datalist-non-strict'
      ) COLLATE utf8mb4_unicode_ci NOT NULL,
  description text,
  parent_uuid_v BINARY(16) AS (COALESCE(parent_uuid, 0x00000000000000000000000000000000)) STORED,
  PRIMARY KEY (uuid),
  UNIQUE KEY unique_name_parent_uuid (key_name, parent_uuid_v)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;

ALTER TABLE director_property
  ADD COLUMN parent_uuid_v BINARY(16) AS (
    COALESCE(parent_uuid, 0x00000000000000000000000000000000)
) STORED;

ALTER TABLE director_property
  ADD UNIQUE KEY unique_name_parent_uuid (key_name, parent_uuid_v);

CREATE TABLE icinga_host_property (
  host_uuid binary(16) NOT NULL,
  property_uuid binary(16) NOT NULL,
  required enum('y', 'n') NOT NULL DEFAULT 'n',
  PRIMARY KEY (host_uuid, property_uuid),
  CONSTRAINT icinga_host_property_host
    FOREIGN KEY host(host_uuid)
      REFERENCES icinga_host (uuid)
      ON DELETE CASCADE
      ON UPDATE CASCADE,
  CONSTRAINT icinga_host_custom_property
    FOREIGN KEY property(property_uuid)
      REFERENCES director_property (uuid)
      ON DELETE CASCADE
      ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;

CREATE TABLE icinga_service_property (
  service_uuid binary(16) NOT NULL,
  property_uuid binary(16) NOT NULL,
  required enum('y', 'n') NOT NULL DEFAULT 'n',
  PRIMARY KEY (service_uuid, property_uuid),
  CONSTRAINT icinga_service_property_service
    FOREIGN KEY service(service_uuid)
      REFERENCES icinga_service (uuid)
      ON DELETE CASCADE
      ON UPDATE CASCADE,
  CONSTRAINT icinga_service_custom_property
    FOREIGN KEY property(property_uuid)
      REFERENCES director_property (uuid)
      ON DELETE CASCADE
      ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;

CREATE TABLE icinga_command_property (
 command_uuid binary(16) NOT NULL,
 property_uuid binary(16) NOT NULL,
 required enum('y', 'n') NOT NULL DEFAULT 'n',
 PRIMARY KEY (command_uuid, property_uuid),
 CONSTRAINT icinga_command_property_command
   FOREIGN KEY command(command_uuid)
     REFERENCES icinga_command (uuid)
     ON DELETE CASCADE
     ON UPDATE CASCADE,
 CONSTRAINT icinga_command_custom_property
   FOREIGN KEY property(property_uuid)
     REFERENCES director_property (uuid)
     ON DELETE CASCADE
     ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;

CREATE TABLE icinga_notification_property (
 notification_uuid binary(16) NOT NULL,
 property_uuid binary(16) NOT NULL,
 required enum('y', 'n') NOT NULL DEFAULT 'n',
 PRIMARY KEY (notification_uuid, property_uuid),
 CONSTRAINT icinga_notification_property_notification
   FOREIGN KEY notification(notification_uuid)
     REFERENCES icinga_notification (uuid)
     ON DELETE CASCADE
     ON UPDATE CASCADE,
 CONSTRAINT icinga_notification_custom_property
   FOREIGN KEY property(property_uuid)
     REFERENCES director_property (uuid)
     ON DELETE CASCADE
     ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;

CREATE TABLE icinga_service_set_property (
 service_set_uuid binary(16) NOT NULL,
 property_uuid binary(16) NOT NULL,
 required enum('y', 'n') NOT NULL DEFAULT 'n',
 PRIMARY KEY (service_set_uuid, property_uuid),
 CONSTRAINT icinga_service_set_property_service_set
   FOREIGN KEY service_set(service_set_uuid)
     REFERENCES icinga_service_set (uuid)
     ON DELETE CASCADE
     ON UPDATE CASCADE,
 CONSTRAINT icinga_service_set_custom_property
   FOREIGN KEY property(property_uuid)
    REFERENCES director_property (uuid)
    ON DELETE CASCADE
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;

CREATE TABLE icinga_user_property (
 user_uuid binary(16) NOT NULL,
 property_uuid binary(16) NOT NULL,
 required enum('y', 'n') NOT NULL DEFAULT 'n',
 PRIMARY KEY (user_uuid, property_uuid),
 CONSTRAINT icinga_user_property_user
   FOREIGN KEY user (user_uuid)
     REFERENCES icinga_user (uuid)
     ON DELETE CASCADE
     ON UPDATE CASCADE,
 CONSTRAINT icinga_user_custom_property
   FOREIGN KEY property(property_uuid)
     REFERENCES director_property (uuid)
     ON DELETE CASCADE
     ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;

ALTER TABLE director_datalist
    ADD UNIQUE KEY (uuid);

CREATE TABLE director_property_datalist (
 list_uuid binary(16) NOT NULL,
 property_uuid binary(16) NOT NULL,
 PRIMARY KEY (list_uuid, property_uuid),
 CONSTRAINT director_list_property_list
     FOREIGN KEY list (list_uuid)
         REFERENCES director_datalist (uuid)
         ON DELETE CASCADE
         ON UPDATE CASCADE,
 CONSTRAINT director_property_list_property
     FOREIGN KEY property (property_uuid)
         ON DELETE CASCADE
         REFERENCES director_property (uuid)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_bin;

ALTER TABLE icinga_host_var
  ADD COLUMN property_uuid binary(16);

ALTER TABLE icinga_service_var
  ADD COLUMN property_uuid binary(16);

ALTER TABLE icinga_command_var
  ADD COLUMN property_uuid binary(16);

ALTER TABLE icinga_notification_var
  ADD COLUMN property_uuid binary(16);

ALTER TABLE icinga_service_set_var
  ADD COLUMN property_uuid binary(16);

ALTER TABLE icinga_user_var
  ADD COLUMN property_uuid binary(16);

INSERT INTO director_schema_migration
(schema_version, migration_time)
VALUES (191, NOW());
