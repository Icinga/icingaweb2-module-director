
-- dropping old tables, as they have never been used

DROP TABLE import_row_modifier_setting;
DROP TABLE import_row_modifier;

CREATE TABLE import_row_modifier (
  id INT(10) UNSIGNED AUTO_INCREMENT NOT NULL,
  source_id INT(10) UNSIGNED NOT NULL,
  property_name VARCHAR(255) NOT NULL,
  provider_class VARCHAR(72) NOT NULL,
  priority SMALLINT UNSIGNED NOT NULL,
  PRIMARY KEY (id),
  KEY search_idx (property_name),
  CONSTRAINT row_modifier_import_source
    FOREIGN KEY source (source_id)
    REFERENCES import_source (id)
    ON DELETE CASCADE
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE import_row_modifier_setting (
  row_modifier_id INT UNSIGNED NOT NULL,
  setting_name VARCHAR(64) NOT NULL,
  setting_value TEXT DEFAULT NULL,
  PRIMARY KEY (row_modifier_id, setting_name),
  CONSTRAINT row_modifier_settings
    FOREIGN KEY row_modifier (row_modifier_id)
    REFERENCES import_row_modifier (id)
    ON DELETE CASCADE
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

INSERT INTO director_schema_migration
  SET migration_time = NOW(),
      schema_version = 66;

