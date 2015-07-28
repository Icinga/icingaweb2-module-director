CREATE TABLE director_datafield_setting (
  datafield_id INT(10) UNSIGNED NOT NULL,
  setting_name VARCHAR(64) NOT NULL,
  setting_value TEXT NOT NULL,
  PRIMARY KEY (datafield_id, setting_name),
  CONSTRAINT datafield_id_settings
  FOREIGN KEY datafield (datafield_id)
  REFERENCES director_datafield (id)
    ON DELETE CASCADE
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
