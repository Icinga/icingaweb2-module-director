CREATE TABLE icinga_endpoint_inheritance (
  endpoint_id INT(10) UNSIGNED NOT NULL,
  parent_endpoint_id INT(10) UNSIGNED NOT NULL,
  weight MEDIUMINT UNSIGNED DEFAULT NULL,
  PRIMARY KEY (endpoint_id, parent_endpoint_id),
  UNIQUE KEY unique_order (endpoint_id, weight),
  CONSTRAINT icinga_endpoint_inheritance_endpoint
  FOREIGN KEY endpoint (endpoint_id)
  REFERENCES icinga_endpoint (id)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT icinga_endpoint_inheritance_parent_endpoint
  FOREIGN KEY endpoint (parent_endpoint_id)
  REFERENCES icinga_endpoint (id)
    ON DELETE RESTRICT
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
