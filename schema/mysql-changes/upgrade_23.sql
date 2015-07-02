DROP TABLE director_datatype;

CREATE TABLE director_datafield (
  id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  varname VARCHAR(64) NOT NULL,
  caption VARCHAR(255) NOT NULL,
  description TEXT DEFAULT NULL,
  datatype varchar(255) NOT NULL,
-- datatype_param? multiple ones?
  format enum ('string', 'json', 'expression'),
  PRIMARY KEY (id),
  KEY search_idx (varname)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE icinga_host_field;

CREATE TABLE icinga_host_field (
  host_id INT(10) UNSIGNED NOT NULL COMMENT 'Makes only sense for templates',
  datafield_id INT(10) UNSIGNED NOT NULL,
  is_required ENUM('y', 'n') DEFAULT NULL,
  PRIMARY KEY (host_id, datafield_id),
  CONSTRAINT icinga_host_field_host
  FOREIGN KEY host(host_id)
    REFERENCES icinga_host (id)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT icinga_host_field_datafield
  FOREIGN KEY datafield(datafield_id)
  REFERENCES director_datafield (id)
    ON DELETE CASCADE
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE icinga_service_field;

CREATE TABLE icinga_service_field (
  service_id INT(10) UNSIGNED NOT NULL COMMENT 'Makes only sense for templates',
  datafield_id INT(10) UNSIGNED NOT NULL,
  is_required ENUM('y', 'n') DEFAULT NULL,
  PRIMARY KEY (service_id, datafield_id),
  CONSTRAINT icinga_service_field_service
  FOREIGN KEY service(service_id)
  REFERENCES icinga_service (id)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT icinga_service_field_datafield
  FOREIGN KEY datafield(datafield_id)
  REFERENCES director_datafield (id)
    ON DELETE CASCADE
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;