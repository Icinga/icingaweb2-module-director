CREATE TABLE icinga_command_inheritance (
  command_id integer NOT NULL,
  parent_command_id integer NOT NULL,
  weight integer DEFAULT NULL,
  PRIMARY KEY (command_id, parent_command_id),
  CONSTRAINT icinga_command_inheritance_command
  FOREIGN KEY (command_id)
  REFERENCES icinga_command (id)
  ON DELETE CASCADE
  ON UPDATE CASCADE,
  CONSTRAINT icinga_command_inheritance_parent_command
  FOREIGN KEY (parent_command_id)
  REFERENCES icinga_command (id)
  ON DELETE RESTRICT
  ON UPDATE CASCADE
);

CREATE UNIQUE INDEX command_inheritance_unique_order ON icinga_command_inheritance (command_id, weight);
CREATE INDEX command_inheritance_command ON icinga_command_inheritance (command_id);
CREATE INDEX command_inheritance_command_parent ON icinga_command_inheritance (parent_command_id);
