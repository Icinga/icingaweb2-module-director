CREATE TABLE icinga_dependency (
  id INT(10) UNSIGNED AUTO_INCREMENT NOT NULL,
  object_name VARCHAR(255) DEFAULT NULL,
  object_type ENUM('object', 'template', 'apply') NOT NULL,
  disabled ENUM('y', 'n') NOT NULL DEFAULT 'n',
  apply_to ENUM('host', 'service') DEFAULT NULL,
  parent_host_id INT(10) UNSIGNED DEFAULT NULL,
  parent_service_id INT(10) UNSIGNED DEFAULT NULL,
  child_host_id INT(10) UNSIGNED DEFAULT NULL,
  child_service_id INT(10) UNSIGNED DEFAULT NULL,
  disable_checks ENUM('y', 'n') DEFAULT NULL,
  disable_notifications ENUM('y', 'n') DEFAULT NULL,
  ignore_soft_states ENUM('y', 'n') DEFAULT NULL,
  period_id INT(10) UNSIGNED DEFAULT NULL,
  zone_id INT(10) UNSIGNED DEFAULT NULL,
  assign_filter TEXT DEFAULT NULL,
  parent_service_by_name VARCHAR(255) DEFAULT NULL,
  PRIMARY KEY (id),
  CONSTRAINT icinga_dependency_parent_host
    FOREIGN KEY parent_host (parent_host_id)
    REFERENCES icinga_host (id)
    ON DELETE RESTRICT
    ON UPDATE CASCADE,
  CONSTRAINT icinga_dependency_parent_service
    FOREIGN KEY parent_service (parent_service_id)
    REFERENCES icinga_service (id)
    ON DELETE RESTRICT
    ON UPDATE CASCADE,
  CONSTRAINT icinga_dependency_child_host
    FOREIGN KEY child_host (child_host_id)
    REFERENCES icinga_host (id)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT icinga_dependency_child_service
    FOREIGN KEY child_service (child_service_id)
    REFERENCES icinga_service (id)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT icinga_dependency_period
    FOREIGN KEY period (period_id)
    REFERENCES icinga_timeperiod (id)
    ON DELETE RESTRICT
    ON UPDATE CASCADE,
  CONSTRAINT icinga_dependency_zone
    FOREIGN KEY zone (zone_id)
    REFERENCES icinga_zone (id)
    ON DELETE RESTRICT
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE icinga_dependency_inheritance (
  dependency_id INT(10) UNSIGNED NOT NULL,
  parent_dependency_id INT(10) UNSIGNED NOT NULL,
  weight MEDIUMINT UNSIGNED DEFAULT NULL,
  PRIMARY KEY (dependency_id, parent_dependency_id),
  UNIQUE KEY unique_order (dependency_id, weight),
  CONSTRAINT icinga_dependency_inheritance_dependency
  FOREIGN KEY dependency (dependency_id)
  REFERENCES icinga_dependency (id)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT icinga_dependency_inheritance_parent_dependency
  FOREIGN KEY parent_dependency (parent_dependency_id)
  REFERENCES icinga_dependency (id)
    ON DELETE RESTRICT
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE icinga_dependency_states_set (
  dependency_id INT(10) UNSIGNED NOT NULL,
  property ENUM(
    'OK',
    'Warning',
    'Critical',
    'Unknown',
    'Up',
    'Down'
  ) NOT NULL,
  merge_behaviour ENUM('override', 'extend', 'blacklist') NOT NULL DEFAULT 'override'
    COMMENT 'override: = [], extend: += [], blacklist: -= []',
  PRIMARY KEY (dependency_id, property, merge_behaviour),
  CONSTRAINT icinga_dependency_states_set_dependency
    FOREIGN KEY icinga_dependency (dependency_id)
    REFERENCES icinga_dependency (id)
    ON DELETE CASCADE
    ON UPDATE CASCADE
)  ENGINE=InnoDB;

INSERT INTO director_schema_migration
  (schema_version, migration_time)
  VALUES (144, NOW());
