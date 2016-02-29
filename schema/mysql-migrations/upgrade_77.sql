CREATE TABLE icinga_notification_states_set (
  notification_id INT(10) UNSIGNED NOT NULL,
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
  PRIMARY KEY (notification_id, property, merge_behaviour),
  CONSTRAINT icinga_notification_states_set_notification
    FOREIGN KEY icinga_notification (notification_id)
    REFERENCES icinga_notification (id)
    ON DELETE CASCADE
    ON UPDATE CASCADE
)  ENGINE=InnoDB;

CREATE TABLE icinga_notification_types_set (
  notification_id INT(10) UNSIGNED NOT NULL,
  property ENUM(
    'DowntimeStart',
    'DowntimeEnd',
    'DowntimeRemoved',
    'Custom',
    'Acknowledgement',
    'Problem',
    'Recovery',
    'FlappingStart',
    'FlappingEnd'
  ) NOT NULL,
  merge_behaviour ENUM('override', 'extend', 'blacklist') NOT NULL DEFAULT 'override'
    COMMENT 'override: = [], extend: += [], blacklist: -= []',
  PRIMARY KEY (notification_id, property, merge_behaviour),
  CONSTRAINT icinga_notification_types_set_notification
    FOREIGN KEY icinga_notification (notification_id)
    REFERENCES icinga_notification (id)
    ON DELETE CASCADE
    ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE icinga_notification_var (
  notification_id INT(10) UNSIGNED NOT NULL,
  varname VARCHAR(255) DEFAULT NULL,
  varvalue TEXT DEFAULT NULL,
  format enum ('string', 'json', 'expression'),
  PRIMARY KEY (notification_id, varname),
  key search_idx (varname),
  CONSTRAINT icinga_notification_var_notification
    FOREIGN KEY notification (notification_id)
    REFERENCES icinga_notification (id)
    ON DELETE CASCADE
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE icinga_notification_inheritance (
  notification_id INT(10) UNSIGNED NOT NULL,
  parent_notification_id INT(10) UNSIGNED NOT NULL,
  weight MEDIUMINT UNSIGNED DEFAULT NULL,
  PRIMARY KEY (notification_id, parent_notification_id),
  UNIQUE KEY unique_order (notification_id, weight),
  CONSTRAINT icinga_notification_inheritance_notification
  FOREIGN KEY host (notification_id)
  REFERENCES icinga_notification (id)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT icinga_notification_inheritance_parent_notification
  FOREIGN KEY host (parent_notification_id)
  REFERENCES icinga_notification (id)
    ON DELETE RESTRICT
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

INSERT INTO director_schema_migration
  SET migration_time = NOW(),
      schema_version = 77;
