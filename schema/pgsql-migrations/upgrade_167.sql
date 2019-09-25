CREATE TABLE director_daemon_info (
  instance_uuid_hex character varying(32) NOT NULL, -- random by daemon
  schema_version SMALLINT NOT NULL,
  fqdn character varying(255) NOT NULL,
  username character varying(64) NOT NULL,
  pid integer NOT NULL,
  binary_path character varying(128) NOT NULL,
  binary_realpath character varying(128) NOT NULL,
  php_binary_path character varying(128) NOT NULL,
  php_binary_realpath character varying(128) NOT NULL,
  php_version character varying(64) NOT NULL,
  php_integer_size SMALLINT NOT NULL,
  running_with_systemd enum_boolean DEFAULT NULL,
  ts_started bigint NOT NULL,
  ts_stopped bigint DEFAULT NULL,
  ts_last_modification bigint DEFAULT NULL,
  ts_last_update bigint NOT NULL,
  process_info text NOT NULL,
  PRIMARY KEY (instance_uuid_hex)
);

INSERT INTO director_schema_migration
  (schema_version, migration_time)
  VALUES (167, NOW());
