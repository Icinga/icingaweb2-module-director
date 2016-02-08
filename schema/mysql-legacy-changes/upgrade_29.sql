DROP TABLE sync_modifier_param;
DROP TABLE sync_modifier;

CREATE TABLE import_row_modifier (
  id INT(10) UNSIGNED AUTO_INCREMENT NOT NULL,
  property_id INT(10) UNSIGNED NOT NULL,
  provider_class VARCHAR(72) NOT NULL,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE import_row_modifier_settings (
  modifier_id INT UNSIGNED NOT NULL,
  settings_name VARCHAR(64) NOT NULL,
  settings_value TEXT DEFAULT NULL,
  PRIMARY KEY (modifier_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
