
CREATE TABLE sync_rule (
  id INT(10) UNSIGNED AUTO_INCREMENT NOT NULL,
  rule_name VARCHAR(255) NOT NULL,
  object_type ENUM('host', 'user') NOT NULL,
  update_policy ENUM('merge', 'override', 'ignore') NOT NULL,
  purge_existing ENUM('y', 'n') NOT NULL DEFAULT 'n',
  filter_expression TEXT NOT NULL,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE sync_property (
  id INT(10) UNSIGNED AUTO_INCREMENT NOT NULL,
  rule_id INT(10) UNSIGNED NOT NULL,
  source_id INT(10) UNSIGNED NOT NULL,
  source_expression VARCHAR(255) NOT NULL,
  destination_field VARCHAR(64),
  priority SMALLINT UNSIGNED NOT NULL,
  filter_expression TEXT DEFAULT NULL,
  merge_policy ENUM('override', 'merge') NOT NULL,
  PRIMARY KEY (id),
  CONSTRAINT sync_property_rule
    FOREIGN KEY sync_rule (rule_id)
    REFERENCES sync_rule (id)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT sync_property_source
    FOREIGN KEY import_source (source_id)
    REFERENCES import_source (id)
    ON DELETE RESTRICT
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE sync_modifier (
  id INT(10) UNSIGNED AUTO_INCREMENT NOT NULL,
  property_id INT(10) UNSIGNED NOT NULL,
  provider_class VARCHAR(72) NOT NULL,
  PRIMARY KEY (id),
  CONSTRAINT sync_modifier_property
    FOREIGN KEY sync_property (property_id)
    REFERENCES sync_property (id)
    ON DELETE CASCADE
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE sync_modifier_param (
  modifier_id INT UNSIGNED NOT NULL,
  param_name VARCHAR(64) NOT NULL,
  param_value TEXT DEFAULT NULL,
  PRIMARY KEY (modifier_id, param_name),
  CONSTRAINT sync_modifier_param_modifier
    FOREIGN KEY modifier (modifier_id)
    REFERENCES sync_modifier (id)
    ON DELETE CASCADE
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


