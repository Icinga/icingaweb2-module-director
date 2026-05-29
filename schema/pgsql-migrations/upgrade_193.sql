CREATE TYPE enum_property_value_type AS ENUM(
  'string',
  'number',
  'bool',
  'fixed-array',
  'dynamic-array',
  'fixed-dictionary',
  'dynamic-dictionary',
  'datalist-strict',
  'datalist-non-strict'
);

CREATE TABLE director_property (
  uuid bytea CHECK(LENGTH(uuid) = 16) NOT NULL,
  parent_uuid bytea CHECK(LENGTH(parent_uuid) = 16) DEFAULT NULL,
  key_name character varying(255) NOT NULL,
  label character varying(255) DEFAULT NULL,
  description text DEFAULT NULL,
  value_type enum_property_value_type NOT NULL,
  category_id integer DEFAULT NULL,
  PRIMARY KEY (uuid),
  CONSTRAINT director_property_category
    FOREIGN KEY (category_id)
    REFERENCES director_datafield_category (id)
    ON DELETE RESTRICT
    ON UPDATE CASCADE
);

-- Unique key_name at root level (no parent)
CREATE UNIQUE INDEX unique_property_name_root
  ON director_property (key_name)
  WHERE parent_uuid IS NULL;

-- Unique (key_name, parent_uuid) for nested properties
CREATE UNIQUE INDEX unique_property_name_parent
  ON director_property (key_name, parent_uuid)
  WHERE parent_uuid IS NOT NULL;

CREATE TABLE icinga_host_property (
  host_uuid bytea CHECK(LENGTH(host_uuid) = 16) NOT NULL,
  property_uuid bytea CHECK(LENGTH(property_uuid) = 16) NOT NULL,
  required enum_boolean NOT NULL DEFAULT 'n',
  PRIMARY KEY (host_uuid, property_uuid),
  CONSTRAINT icinga_host_property_host
    FOREIGN KEY (host_uuid)
      REFERENCES icinga_host (uuid)
      ON DELETE CASCADE
      ON UPDATE CASCADE,
  CONSTRAINT icinga_host_custom_property
    FOREIGN KEY (property_uuid)
      REFERENCES director_property (uuid)
      ON DELETE CASCADE
      ON UPDATE CASCADE
);

CREATE TABLE icinga_service_property (
  service_uuid bytea CHECK(LENGTH(service_uuid) = 16) NOT NULL,
  property_uuid bytea CHECK(LENGTH(property_uuid) = 16) NOT NULL,
  required enum_boolean NOT NULL DEFAULT 'n',
  PRIMARY KEY (service_uuid, property_uuid),
  CONSTRAINT icinga_service_property_service
    FOREIGN KEY (service_uuid)
      REFERENCES icinga_service (uuid)
      ON DELETE CASCADE
      ON UPDATE CASCADE,
  CONSTRAINT icinga_service_custom_property
    FOREIGN KEY (property_uuid)
      REFERENCES director_property (uuid)
      ON DELETE CASCADE
      ON UPDATE CASCADE
);

CREATE TABLE icinga_command_property (
  command_uuid bytea CHECK(LENGTH(command_uuid) = 16) NOT NULL,
  property_uuid bytea CHECK(LENGTH(property_uuid) = 16) NOT NULL,
  required enum_boolean NOT NULL DEFAULT 'n',
  PRIMARY KEY (command_uuid, property_uuid),
  CONSTRAINT icinga_command_property_command
    FOREIGN KEY (command_uuid)
      REFERENCES icinga_command (uuid)
      ON DELETE CASCADE
      ON UPDATE CASCADE,
  CONSTRAINT icinga_command_custom_property
    FOREIGN KEY (property_uuid)
      REFERENCES director_property (uuid)
      ON DELETE CASCADE
      ON UPDATE CASCADE
);

CREATE TABLE icinga_notification_property (
  notification_uuid bytea CHECK(LENGTH(notification_uuid) = 16) NOT NULL,
  property_uuid bytea CHECK(LENGTH(property_uuid) = 16) NOT NULL,
  required enum_boolean NOT NULL DEFAULT 'n',
  PRIMARY KEY (notification_uuid, property_uuid),
  CONSTRAINT icinga_notification_property_notification
    FOREIGN KEY (notification_uuid)
      REFERENCES icinga_notification (uuid)
      ON DELETE CASCADE
      ON UPDATE CASCADE,
  CONSTRAINT icinga_notification_custom_property
    FOREIGN KEY (property_uuid)
      REFERENCES director_property (uuid)
      ON DELETE CASCADE
      ON UPDATE CASCADE
);

CREATE TABLE icinga_service_set_property (
  service_set_uuid bytea CHECK(LENGTH(service_set_uuid) = 16) NOT NULL,
  property_uuid bytea CHECK(LENGTH(property_uuid) = 16) NOT NULL,
  required enum_boolean NOT NULL DEFAULT 'n',
  PRIMARY KEY (service_set_uuid, property_uuid),
  CONSTRAINT icinga_service_set_property_service_set
    FOREIGN KEY (service_set_uuid)
      REFERENCES icinga_service_set (uuid)
      ON DELETE CASCADE
      ON UPDATE CASCADE,
  CONSTRAINT icinga_service_set_custom_property
    FOREIGN KEY (property_uuid)
      REFERENCES director_property (uuid)
      ON DELETE CASCADE
      ON UPDATE CASCADE
);

CREATE TABLE icinga_user_property (
  user_uuid bytea CHECK(LENGTH(user_uuid) = 16) NOT NULL,
  property_uuid bytea CHECK(LENGTH(property_uuid) = 16) NOT NULL,
  required enum_boolean NOT NULL DEFAULT 'n',
  PRIMARY KEY (user_uuid, property_uuid),
  CONSTRAINT icinga_user_property_user
    FOREIGN KEY (user_uuid)
      REFERENCES icinga_user (uuid)
      ON DELETE CASCADE
      ON UPDATE CASCADE,
  CONSTRAINT icinga_user_custom_property
    FOREIGN KEY (property_uuid)
      REFERENCES director_property (uuid)
      ON DELETE CASCADE
      ON UPDATE CASCADE
);

CREATE UNIQUE INDEX IF NOT EXISTS director_datalist_uuid_unique
  ON director_datalist (uuid);

CREATE TABLE director_property_datalist (
  list_uuid bytea CHECK(LENGTH(list_uuid) = 16) NOT NULL,
  property_uuid bytea CHECK(LENGTH(property_uuid) = 16) NOT NULL,
  PRIMARY KEY (list_uuid, property_uuid),
  CONSTRAINT director_list_property_list
    FOREIGN KEY (list_uuid)
      REFERENCES director_datalist (uuid)
      ON DELETE CASCADE
      ON UPDATE CASCADE,
  CONSTRAINT director_property_list_property
    FOREIGN KEY (property_uuid)
      REFERENCES director_property (uuid)
      ON DELETE CASCADE
      ON UPDATE CASCADE
);

ALTER TABLE icinga_host_var
  ADD COLUMN property_uuid bytea CHECK(LENGTH(property_uuid) = 16) DEFAULT NULL;

ALTER TABLE icinga_service_var
  ADD COLUMN property_uuid bytea CHECK(LENGTH(property_uuid) = 16) DEFAULT NULL;

ALTER TABLE icinga_command_var
  ADD COLUMN property_uuid bytea CHECK(LENGTH(property_uuid) = 16) DEFAULT NULL;

ALTER TABLE icinga_notification_var
  ADD COLUMN property_uuid bytea CHECK(LENGTH(property_uuid) = 16) DEFAULT NULL;

ALTER TABLE icinga_service_set_var
  ADD COLUMN property_uuid bytea CHECK(LENGTH(property_uuid) = 16) DEFAULT NULL;

ALTER TABLE icinga_user_var
  ADD COLUMN property_uuid bytea CHECK(LENGTH(property_uuid) = 16) DEFAULT NULL;

INSERT INTO director_schema_migration
  (schema_version, migration_time)
  VALUES (193, NOW());
