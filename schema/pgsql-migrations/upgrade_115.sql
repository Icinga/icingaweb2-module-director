CREATE TABLE icinga_service_set_inheritance (
  service_set_id integer NOT NULL,
  parent_service_set_id integer NOT NULL,
  weight integer DEFAULT NULL,
  PRIMARY KEY (service_set_id, parent_service_set_id),
  CONSTRAINT icinga_service_set_inheritance_set
  FOREIGN KEY (service_set_id)
  REFERENCES icinga_service_set (id)
  ON DELETE CASCADE
  ON UPDATE CASCADE,
  CONSTRAINT icinga_service_set_inheritance_parent
  FOREIGN KEY (parent_service_set_id)
  REFERENCES icinga_service_set (id)
  ON DELETE RESTRICT
  ON UPDATE CASCADE
);

CREATE UNIQUE INDEX service_set_inheritance_unique_order ON icinga_service_set_inheritance (service_set_id, weight);
CREATE INDEX service_set_inheritance_set ON icinga_service_set_inheritance (service_set_id);
CREATE INDEX service_set_inheritance_parent ON icinga_service_set_inheritance (parent_service_set_id);


ALTER TABLE icinga_service_set ALTER COLUMN host_id DROP NOT NULL;
ALTER TABLE icinga_service_set ALTER COLUMN description DROP NOT NULL;

INSERT INTO director_schema_migration
  (schema_version, migration_time)
  VALUES (115, NOW());
