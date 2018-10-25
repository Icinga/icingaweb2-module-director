CREATE TABLE icinga_servicegroup_service_resolved (
  servicegroup_id integer NOT NULL,
  service_id integer NOT NULL,
  PRIMARY KEY (servicegroup_id, service_id),
  CONSTRAINT icinga_servicegroup_service_resolved_service
  FOREIGN KEY (service_id)
    REFERENCES icinga_service (id)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT icinga_servicegroup_service_resolved_servicegroup
  FOREIGN KEY (servicegroup_id)
    REFERENCES icinga_servicegroup (id)
    ON DELETE CASCADE
    ON UPDATE CASCADE
);

CREATE INDEX servicegroup_service_resolved_service ON icinga_servicegroup_service_resolved (service_id);
CREATE INDEX servicegroup_service_resolved_servicegroup ON icinga_servicegroup_service_resolved (servicegroup_id);

INSERT INTO director_schema_migration
  (schema_version, migration_time)
  VALUES (155, NOW());
