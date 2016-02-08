CREATE TABLE icinga_zone_inheritance (
  zone_id INT(10) UNSIGNED NOT NULL,
  parent_zone_id INT(10) UNSIGNED NOT NULL,
  weight MEDIUMINT UNSIGNED DEFAULT NULL,
  PRIMARY KEY (zone_id, parent_zone_id),
  UNIQUE KEY unique_order (zone_id, weight),
  CONSTRAINT icinga_zone_inheritance_zone
  FOREIGN KEY zone (zone_id)
  REFERENCES icinga_zone (id)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT icinga_zone_inheritance_parent_zone
  FOREIGN KEY zone (parent_zone_id)
  REFERENCES icinga_zone (id)
    ON DELETE RESTRICT
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
