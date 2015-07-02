CREATE TABLE icinga_host_field (
  host_id INT(10) UNSIGNED NOT NULL COMMENT 'Makes only sense for templates',
  fieldname VARCHAR(64) NOT NULL,
  caption VARCHAR(255) NOT NULL,
  datatype_id INT(10) UNSIGNED NOT NULL,
-- datatype_param? multiple ones?
  default_value TEXT DEFAULT NULL,
  format enum ('string', 'json', 'expression'),
  PRIMARY KEY (host_id, fieldname),
  KEY search_idx (fieldname),
  CONSTRAINT icinga_host_field_host
  FOREIGN KEY host(host_id)
  REFERENCES icinga_host (id)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT icinga_host_field_datatype
  FOREIGN KEY datatype (datatype_id)
  REFERENCES director_datatype (id)
    ON DELETE RESTRICT
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE icinga_service_field (
  service_id INT(10) UNSIGNED NOT NULL COMMENT 'Makes only sense for templates',
  fieldname VARCHAR(64) NOT NULL,
  caption VARCHAR(255) NOT NULL,
  datatype_id INT(10) UNSIGNED NOT NULL,
  -- datatype_param? multiple ones?
  default_value TEXT DEFAULT NULL,
  format enum ('string', 'json', 'expression'),
  PRIMARY KEY (service_id, fieldname),
  KEY search_idx (fieldname),
  CONSTRAINT icinga_service_field_service
    FOREIGN KEY service (service_id)
    REFERENCES icinga_service (id)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT icinga_service_field_datatype
    FOREIGN KEY datatype (datatype_id)
    REFERENCES director_datatype (id)
    ON DELETE RESTRICT
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
