CREATE TABLE icinga_timeperiod_inheritance (
  timeperiod_id INT(10) UNSIGNED NOT NULL,
  parent_timeperiod_id INT(10) UNSIGNED NOT NULL,
  weight MEDIUMINT UNSIGNED DEFAULT NULL,
  PRIMARY KEY (timeperiod_id, parent_timeperiod_id),
  UNIQUE KEY unique_order (timeperiod_id, weight),
  CONSTRAINT icinga_timeperiod_inheritance_timeperiod
  FOREIGN KEY host (timeperiod_id)
  REFERENCES icinga_timeperiod (id)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT icinga_timeperiod_inheritance_parent_timeperiod
  FOREIGN KEY host (parent_timeperiod_id)
  REFERENCES icinga_timeperiod (id)
    ON DELETE RESTRICT
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

