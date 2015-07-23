DROP TABLE import_row_modifier_settings;

CREATE TABLE import_row_modifier_setting (
  modifier_id INT UNSIGNED NOT NULL,
  setting_name VARCHAR(64) NOT NULL,
  setting_value TEXT DEFAULT NULL,
  PRIMARY KEY (modifier_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
