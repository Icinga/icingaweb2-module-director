DROP TABLE icinga_user_states_set;

CREATE TABLE icinga_user_states_set (
  user_id INT(10) UNSIGNED NOT NULL,
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
  PRIMARY KEY (user_id, property, merge_behaviour),
  CONSTRAINT icinga_user_states_set_user
    FOREIGN KEY icinga_user (user_id)
    REFERENCES icinga_user (id)
    ON DELETE CASCADE
    ON UPDATE CASCADE
)  ENGINE=InnoDB;

DROP TABLE icinga_user_filters_set;

CREATE TABLE icinga_user_filters_set (
  user_id INT(10) UNSIGNED NOT NULL,
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
  PRIMARY KEY (user_id, property, merge_behaviour),
  CONSTRAINT icinga_user_filters_set_user
    FOREIGN KEY icinga_user (user_id)
    REFERENCES icinga_user (id)
    ON DELETE CASCADE
    ON UPDATE CASCADE
) ENGINE=InnoDB;

INSERT INTO director_schema_migration
  SET migration_time = NOW(),
      schema_version = 75;
