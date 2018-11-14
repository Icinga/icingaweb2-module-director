CREATE TABLE director_daemon_info (
  instance_uuid_hex VARCHAR(32) NOT NULL, -- random by daemon
  schema_version SMALLINT UNSIGNED NOT NULL,
  fqdn VARCHAR(255) NOT NULL,
  username VARCHAR(64) NOT NULL,
  pid INT UNSIGNED NOT NULL,
  binary_path VARCHAR(128) NOT NULL,
  binary_realpath VARCHAR(128) NOT NULL,
  php_binary_path VARCHAR(128) NOT NULL,
  php_binary_realpath VARCHAR(128) NOT NULL,
  php_version VARCHAR(64) NOT NULL,
  php_integer_size SMALLINT NOT NULL,
  running_with_systemd ENUM('y', 'n') NOT NULL,
  ts_started BIGINT(20) NOT NULL,
  ts_stopped BIGINT(20) DEFAULT NULL,
  ts_last_modification BIGINT(20) DEFAULT NULL,
  ts_last_update BIGINT(20) DEFAULT NULL,
  process_info MEDIUMTEXT NOT NULL,
  PRIMARY KEY (instance_uuid_hex)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_bin;


INSERT INTO director_schema_migration
  (schema_version, migration_time)
  VALUES (167, NOW());
