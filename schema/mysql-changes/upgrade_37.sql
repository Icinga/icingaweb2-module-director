CREATE TABLE icinga_command_field (
  command_id INT(10) UNSIGNED NOT NULL,
  datafield_id INT(10) UNSIGNED NOT NULL,
  is_required ENUM('y', 'n') NOT NULL,
  PRIMARY KEY (command_id, datafield_id),
  CONSTRAINT icinga_command_field_command_argument
  FOREIGN KEY host(command_id)
    REFERENCES icinga_command_argument (id)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT icinga_command_field_datafield
  FOREIGN KEY datafield(datafield_id)
  REFERENCES director_datafield (id)
    ON DELETE CASCADE
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

