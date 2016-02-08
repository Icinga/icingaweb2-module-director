CREATE TABLE icinga_user_inheritance (
  user_id INT(10) UNSIGNED NOT NULL,
  parent_user_id INT(10) UNSIGNED NOT NULL,
  weight MEDIUMINT UNSIGNED DEFAULT NULL,
  PRIMARY KEY (user_id, parent_user_id),
  UNIQUE KEY unique_order (user_id, weight),
  CONSTRAINT icinga_user_inheritance_user
  FOREIGN KEY host (user_id)
  REFERENCES icinga_user (id)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT icinga_user_inheritance_parent_user
  FOREIGN KEY host (parent_user_id)
  REFERENCES icinga_user (id)
    ON DELETE RESTRICT
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

