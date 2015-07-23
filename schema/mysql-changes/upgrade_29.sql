DROP TABLE sync_modifier IF EXISTS;
DROP TABLE sync_modifier_param IF EXISTS;

CREATE TABLE import_row_modifier (
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

CREATE TABLE import_row_modifier_settings (
  modifier_id INT UNSIGNED NOT NULL,
  settings_name VARCHAR(64) NOT NULL,
  settings_value TEXT DEFAULT NULL,
  PRIMARY KEY (modifier_id, param_name),
  CONSTRAINT sync_modifier_param_modifier
    FOREIGN KEY modifier (modifier_id)
    REFERENCES sync_modifier (id)
    ON DELETE CASCADE
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
