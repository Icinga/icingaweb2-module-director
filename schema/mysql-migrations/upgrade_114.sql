CREATE TABLE icinga_service_set (
  id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  object_name VARCHAR(128) NOT NULL,
  object_type ENUM('object', 'template', 'external_object') NOT NULL,
  host_id INT(10) UNSIGNED DEFAULT NULL,
  description TEXT NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY object_key (object_name, host_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE icinga_service_set_service (
  service_set_id INT(10) UNSIGNED NOT NULL,
  service_id INT(10) UNSIGNED NOT NULL,
  PRIMARY KEY (service_set_id, service_id),
  CONSTRAINT service_set_set
    FOREIGN KEY service_set (service_set_id)
    REFERENCES icinga_service_set (id)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT service_set_service
    FOREIGN KEY service (service_id)
    REFERENCES icinga_service (id)
    ON DELETE RESTRICT
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE icinga_service_set_assignment (
  id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  service_set_id INT(10) UNSIGNED NOT NULL,
  filter_string TEXT NOT NULL,
  assign_type ENUM('assign', 'ignore') NOT NULL DEFAULT 'assign',
  PRIMARY KEY (id),
  CONSTRAINT icinga_service_set_assignment
    FOREIGN KEY service_set (service_set_id)
    REFERENCES icinga_service_set (id)
    ON DELETE CASCADE
    ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE icinga_service_set_var (
  service_set_id INT(10) UNSIGNED NOT NULL,
  varname VARCHAR(255) NOT NULL COLLATE utf8_bin,
  varvalue TEXT DEFAULT NULL,
  format ENUM('string', 'expression', 'json') NOT NULL DEFAULT 'string',
  PRIMARY KEY (service_set_id, varname),
  CONSTRAINT icinga_service_set_var_service
    FOREIGN KEY command (service_set_id)
    REFERENCES icinga_service_set (id)
    ON DELETE CASCADE
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

INSERT INTO director_schema_migration
  (schema_version, migration_time)
  VALUES (114, NOW());
