CREATE TABLE icinga_usergroup_inheritance (
  usergroup_id INT(10) UNSIGNED NOT NULL,
  parent_usergroup_id INT(10) UNSIGNED NOT NULL,
  weight MEDIUMINT UNSIGNED DEFAULT NULL,
  PRIMARY KEY (usergroup_id, parent_usergroup_id),
  UNIQUE KEY unique_order (usergroup_id, weight),
  CONSTRAINT icinga_usergroup_inheritance_usergroup
  FOREIGN KEY usergroup (usergroup_id)
  REFERENCES icinga_usergroup (id)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT icinga_usergroup_inheritance_parent_usergroup
  FOREIGN KEY usergroup (parent_usergroup_id)
  REFERENCES icinga_usergroup (id)
    ON DELETE RESTRICT
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
