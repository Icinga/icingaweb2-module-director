CREATE TABLE icinga_servicegroup_inheritance (
  servicegroup_id INT(10) UNSIGNED NOT NULL,
  parent_servicegroup_id INT(10) UNSIGNED NOT NULL,
  weight MEDIUMINT UNSIGNED DEFAULT NULL,
  PRIMARY KEY (servicegroup_id, parent_servicegroup_id),
  UNIQUE KEY unique_order (servicegroup_id, weight),
  CONSTRAINT icinga_servicegroup_inheritance_servicegroup
  FOREIGN KEY host (servicegroup_id)
  REFERENCES icinga_servicegroup (id)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT icinga_servicegroup_inheritance_parent_servicegroup
  FOREIGN KEY host (parent_servicegroup_id)
  REFERENCES icinga_servicegroup (id)
    ON DELETE RESTRICT
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
