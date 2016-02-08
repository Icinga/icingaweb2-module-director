CREATE TABLE icinga_usergroup_inheritance (
  usergroup_id integer NOT NULL,
  parent_usergroup_id integer NOT NULL,
  weight integer DEFAULT NULL,
  PRIMARY KEY (usergroup_id, parent_usergroup_id),
  CONSTRAINT icinga_usergroup_inheritance_usergroup
  FOREIGN KEY (usergroup_id)
  REFERENCES icinga_usergroup (id)
  ON DELETE CASCADE
  ON UPDATE CASCADE,
  CONSTRAINT icinga_usergroup_inheritance_parent_usergroup
  FOREIGN KEY (parent_usergroup_id)
  REFERENCES icinga_usergroup (id)
  ON DELETE RESTRICT
  ON UPDATE CASCADE
);

CREATE UNIQUE INDEX usergroup_inheritance_unique_order ON icinga_usergroup_inheritance (usergroup_id, weight);
CREATE INDEX usergroup_inheritance_usergroup ON icinga_usergroup_inheritance (usergroup_id);
CREATE INDEX usergroup_inheritance_usergroup_parent ON icinga_usergroup_inheritance (parent_usergroup_id);
