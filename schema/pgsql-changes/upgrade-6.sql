CREATE TABLE icinga_hostgroup_inheritance (
  hostgroup_id integer NOT NULL,
  parent_hostgroup_id integer NOT NULL,
  weight integer DEFAULT NULL,
  PRIMARY KEY (hostgroup_id, parent_hostgroup_id),
  CONSTRAINT icinga_hostgroup_inheritance_hostgroup
  FOREIGN KEY (hostgroup_id)
  REFERENCES icinga_hostgroup (id)
  ON DELETE CASCADE
  ON UPDATE CASCADE,
  CONSTRAINT icinga_hostgroup_inheritance_parent_hostgroup
  FOREIGN KEY (parent_hostgroup_id)
  REFERENCES icinga_hostgroup (id)
  ON DELETE RESTRICT
  ON UPDATE CASCADE
);

CREATE UNIQUE INDEX hostgroup_inheritance_unique_order ON icinga_hostgroup_inheritance (hostgroup_id, weight);
CREATE INDEX hostgroup_inheritance_hostgroup ON icinga_hostgroup_inheritance (hostgroup_id);
CREATE INDEX hostgroup_inheritance_hostgroup_parent ON icinga_hostgroup_inheritance (parent_hostgroup_id);