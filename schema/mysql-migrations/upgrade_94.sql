CREATE TABLE director_job (
  id INT(10) UNSIGNED AUTO_INCREMENT NOT NULL,
  job_name VARCHAR(64) NOT NULL,
  job_class VARCHAR(72) NOT NULL,
  disabled ENUM('y', 'n') NOT NULL DEFAULT 'n',
  run_interval INT(10) UNSIGNED NOT NULL, -- seconds
  last_attempt_succeeded ENUM('y', 'n') DEFAULT NULL,
  ts_last_attempt DATETIME DEFAULT NULL,
  ts_last_error DATETIME DEFAULT NULL,
  last_error_message TEXT,
  PRIMARY KEY (id),
  UNIQUE KEY (job_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE director_job_setting (
  job_id INT UNSIGNED NOT NULL,
  setting_name VARCHAR(64) NOT NULL,
  setting_value TEXT DEFAULT NULL,
  PRIMARY KEY (job_id, setting_name),
  CONSTRAINT job_settings
    FOREIGN KEY director_job (job_id)
    REFERENCES director_job (id)
    ON DELETE CASCADE
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

INSERT INTO director_schema_migration
  (schema_version, migration_time)
  VALUES (94, NOW());
