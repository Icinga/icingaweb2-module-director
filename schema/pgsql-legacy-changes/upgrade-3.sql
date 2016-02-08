CREATE TABLE icinga_service_inheritance (
  service_id integer NOT NULL,
  parent_service_id integer NOT NULL,
  weight integer DEFAULT NULL,
  PRIMARY KEY (service_id, parent_service_id),
  CONSTRAINT icinga_service_inheritance_service
  FOREIGN KEY (service_id)
  REFERENCES icinga_service (id)
  ON DELETE CASCADE
  ON UPDATE CASCADE,
  CONSTRAINT icinga_service_inheritance_parent_service
  FOREIGN KEY (parent_service_id)
  REFERENCES icinga_service (id)
  ON DELETE RESTRICT
  ON UPDATE CASCADE
);

CREATE UNIQUE INDEX service_inheritance_unique_order ON icinga_service_inheritance (service_id, weight);
CREATE INDEX service_inheritance_service ON icinga_service_inheritance (service_id);
CREATE INDEX service_inheritance_service_parent ON icinga_service_inheritance (parent_service_id);

