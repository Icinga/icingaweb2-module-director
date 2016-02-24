CREATE TABLE sync_run (
  id BIGINT(10) UNSIGNED AUTO_INCREMENT NOT NULL,
  rule_id INT(10) UNSIGNED DEFAULT NULL,
  rule_name VARCHAR(255) NOT NULL,
  start_time DATETIME NOT NULL,
  duration_ms INT(10) UNSIGNED NOT NULL,
  objects_deleted INT(10) UNSIGNED DEFAULT 0,
  objects_created INT(10) UNSIGNED DEFAULT 0,
  objects_modified INT(10) UNSIGNED DEFAULT 0,
  first_related_activity VARBINARY(20) DEFAULT NULL,
  last_related_activity VARBINARY(20) DEFAULT NULL,
  PRIMARY KEY (id),
  CONSTRAINT sync_run_rule
    FOREIGN KEY sync_rule (rule_id)
    REFERENCES sync_rule (id)
    ON DELETE SET NULL
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

INSERT INTO director_schema_migration
  SET migration_time = NOW(),
      schema_version = 67;

