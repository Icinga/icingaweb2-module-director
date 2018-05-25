CREATE TABLE icinga_host_service_blacklist(
  host_id integer NOT NULL,
  service_id integer NOT NULL,
  PRIMARY KEY (host_id, service_id),
  CONSTRAINT icinga_host_service__bl_host
  FOREIGN KEY (host_id)
  REFERENCES icinga_host (id)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT icinga_host_service_bl_service
  FOREIGN KEY (service_id)
  REFERENCES icinga_service (id)
    ON DELETE CASCADE
    ON UPDATE CASCADE
);

CREATE INDEX host_service_bl_host ON icinga_host_service_blacklist (host_id);
CREATE INDEX host_service_bl_service ON icinga_host_service_blacklist (service_id);


INSERT INTO director_schema_migration
  (schema_version, migration_time)
  VALUES (147, NOW());
