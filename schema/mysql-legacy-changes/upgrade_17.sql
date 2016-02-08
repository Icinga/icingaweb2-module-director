CREATE TABLE icinga_command_inheritance (
  command_id INT(10) UNSIGNED NOT NULL,
  parent_command_id INT(10) UNSIGNED NOT NULL,
  weight MEDIUMINT UNSIGNED DEFAULT NULL,
  PRIMARY KEY (command_id, parent_command_id),
  UNIQUE KEY unique_order (command_id, weight),
  CONSTRAINT icinga_command_inheritance_command
  FOREIGN KEY command (command_id)
  REFERENCES icinga_command (id)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT icinga_command_inheritance_parent_command
  FOREIGN KEY command (parent_command_id)
  REFERENCES icinga_command (id)
    ON DELETE RESTRICT
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
