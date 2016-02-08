CREATE TABLE icinga_endpoint_inheritance (
  endpoint_id integer NOT NULL,
  parent_endpoint_id integer NOT NULL,
  weight integer DEFAULT NULL,
  PRIMARY KEY (endpoint_id, parent_endpoint_id),
  CONSTRAINT icinga_endpoint_inheritance_endpoint
  FOREIGN KEY (endpoint_id)
  REFERENCES icinga_endpoint (id)
  ON DELETE CASCADE
  ON UPDATE CASCADE,
  CONSTRAINT icinga_endpoint_inheritance_parent_endpoint
  FOREIGN KEY (parent_endpoint_id)
  REFERENCES icinga_endpoint (id)
  ON DELETE RESTRICT
  ON UPDATE CASCADE
);

CREATE UNIQUE INDEX endpoint_inheritance_unique_order ON icinga_endpoint_inheritance (endpoint_id, weight);
CREATE INDEX endpoint_inheritance_endpoint ON icinga_endpoint_inheritance (endpoint_id);
CREATE INDEX endpoint_inheritance_endpoint_parent ON icinga_endpoint_inheritance (parent_endpoint_id);
