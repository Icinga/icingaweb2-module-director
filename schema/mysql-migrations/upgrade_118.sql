CREATE TABLE director_dictionary (
  id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  dictionary_name VARCHAR(255) NOT NULL,
  owner VARCHAR(255) NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY (dictionary_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE director_dictionary_field (
  id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  dictionary_id INT(10) UNSIGNED NOT NULL,
  varname VARCHAR(64) NOT NULL,
  caption VARCHAR(255) NOT NULL,
  description TEXT DEFAULT NULL,
  datatype varchar(255) NOT NULL,
  format enum ('string', 'json', 'expression'),
  is_required ENUM('y','n') NOT NULL,
  allow_multiple ENUM('y','n') NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY (dictionary_id, varname),
  CONSTRAINT dictionary_field_dictionary
    FOREIGN KEY dictionary (dictionary_id)
    REFERENCES director_dictionary (id)
    ON DELETE CASCADE
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE director_dictionary_field_setting (
  dictionary_field_id INT(10) UNSIGNED NOT NULL,
  setting_name VARCHAR(64) NOT NULL,
  setting_value TEXT NOT NULL,
  PRIMARY KEY (dictionary_field_id, setting_name),
  CONSTRAINT dictfield_id_settings
  FOREIGN KEY dictionary_field (dictionary_field_id)
  REFERENCES director_dictionary_field (id)
    ON DELETE CASCADE
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

INSERT INTO director_schema_migration
  (schema_version, migration_time)
  VALUES (118, NOW());