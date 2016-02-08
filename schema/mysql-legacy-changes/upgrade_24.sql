CREATE TABLE import_source (
  id INT(10) UNSIGNED AUTO_INCREMENT NOT NULL,
  source_name VARCHAR(64) NOT NULL,
  provider_class VARCHAR(72) NOT NULL,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE import_source_setting (
  source_id INT(10) UNSIGNED NOT NULL,
  setting_name VARCHAR(64) NOT NULL,
  setting_value VARCHAR(255) NOT NULL,
  PRIMARY KEY (source_id, setting_name),
  CONSTRAINT import_source_settings_source
    FOREIGN KEY source (source_id)
    REFERENCES import_source (id)
    ON DELETE CASCADE
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE imported_rowset (
  checksum VARBINARY(20) NOT NULL,
  PRIMARY KEY (checksum)
) ENGINE=InnoDB;

CREATE TABLE import_run (
  id INT(10) UNSIGNED AUTO_INCREMENT NOT NULL,
  source_id INT(10) UNSIGNED NOT NULL,
  imported_rowset_checksum VARBINARY(20) DEFAULT NULL,
  start_time DATETIME NOT NULL,
  end_time DATETIME DEFAULT NULL,
  succeeded ENUM('y', 'n') DEFAULT NULL,
  PRIMARY KEY (id),
  CONSTRAINT import_run_source
    FOREIGN KEY import_source (source_id)
    REFERENCES import_source (id)
    ON DELETE RESTRICT
    ON UPDATE CASCADE,
  CONSTRAINT import_run_rowset
    FOREIGN KEY rowset (imported_rowset_checksum)
    REFERENCES imported_rowset (checksum)
    ON DELETE RESTRICT
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE imported_row (
  checksum VARBINARY(20) NOT NULL COMMENT 'sha1(object_name;property_checksum;...)',
  object_name VARCHAR(255) NOT NULL,
  PRIMARY KEY (checksum)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE imported_rowset_row (
  rowset_checksum VARBINARY(20) NOT NULL,
  row_checksum VARBINARY(20) NOT NULL,
  PRIMARY KEY (rowset_checksum, row_checksum),
  CONSTRAINT imported_rowset_row_rowset
    FOREIGN KEY rowset_row_rowset (rowset_checksum)
    REFERENCES imported_rowset (checksum)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT imported_rowset_row_row
    FOREIGN KEY rowset_row_rowset (row_checksum)
    REFERENCES imported_row (checksum)
    ON DELETE RESTRICT
    ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE imported_property (
  checksum VARBINARY(20) NOT NULL,
  property_name VARCHAR(64) NOT NULL,
  property_value TEXT NOT NULL,
  format enum ('string', 'expression', 'json'),
  PRIMARY KEY (checksum),
  KEY search_idx (property_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE imported_row_property (
  row_checksum VARBINARY(20) NOT NULL,
  property_checksum VARBINARY(20) NOT NULL,
  PRIMARY KEY (row_checksum, property_checksum),
  CONSTRAINT imported_row_property_row
    FOREIGN KEY row_checksum (row_checksum)
    REFERENCES imported_row (checksum)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT imported_row_property_property
    FOREIGN KEY property_checksum (property_checksum)
    REFERENCES imported_property (checksum)
    ON DELETE RESTRICT
    ON UPDATE CASCADE
) ENGINE=InnoDB;

