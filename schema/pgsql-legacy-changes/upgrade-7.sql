CREATE TABLE icinga_servicegroup_inheritance (
  servicegroup_id integer NOT NULL,
  parent_servicegroup_id integer NOT NULL,
  weight integer DEFAULT NULL,
  PRIMARY KEY (servicegroup_id, parent_servicegroup_id),
  CONSTRAINT icinga_servicegroup_inheritance_servicegroup
  FOREIGN KEY (servicegroup_id)
  REFERENCES icinga_servicegroup (id)
  ON DELETE CASCADE
  ON UPDATE CASCADE,
  CONSTRAINT icinga_servicegroup_inheritance_parent_servicegroup
  FOREIGN KEY (parent_servicegroup_id)
  REFERENCES icinga_servicegroup (id)
  ON DELETE RESTRICT
  ON UPDATE CASCADE
);

CREATE UNIQUE INDEX servicegroup_inheritance_unique_order ON icinga_servicegroup_inheritance (servicegroup_id, weight);
CREATE INDEX servicegroup_inheritance_servicegroup ON icinga_servicegroup_inheritance (servicegroup_id);
CREATE INDEX servicegroup_inheritance_servicegroup_parent ON icinga_servicegroup_inheritance (parent_servicegroup_id);
