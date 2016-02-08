DROP TABLE director_generated_config_file;

DROP TABLE director_generated_config;

CREATE TABLE director_generated_config (
  checksum VARBINARY(20) NOT NULL COMMENT 'SHA1(last_activity_checksum;file_path=checksum;file_path=checksum;...)',
  director_version VARCHAR(64) DEFAULT NULL,
  director_db_version INT(10) DEFAULT NULL,
  duration INT(10) UNSIGNED DEFAULT NULL COMMENT 'Config generation duration (ms)',
  last_activity_checksum VARBINARY(20) NOT NULL,
  PRIMARY KEY (checksum),
  CONSTRAINT director_generated_config_activity
    FOREIGN KEY activity_checksum (last_activity_checksum)
    REFERENCES director_activity_log (checksum)
    ON DELETE RESTRICT
    ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE director_generated_config_file (
  config_checksum VARBINARY(20) NOT NULL,
  file_checksum VARBINARY(20) NOT NULL,
  file_path VARCHAR(64) NOT NULL COMMENT 'e.g. zones/nafta/hosts.conf',
  CONSTRAINT director_generated_config_file_config
    FOREIGN KEY config (config_checksum)
    REFERENCES director_generated_config (checksum)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT director_generated_config_file_file
    FOREIGN KEY checksum (file_checksum)
    REFERENCES director_generated_file (checksum)
    ON DELETE RESTRICT
    ON UPDATE RESTRICT,
  PRIMARY KEY (config_checksum, file_path),
  INDEX search_idx (file_checksum)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

