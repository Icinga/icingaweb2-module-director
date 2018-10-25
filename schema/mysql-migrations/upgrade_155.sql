CREATE TABLE icinga_servicegroup_service_resolved (
  servicegroup_id INT(10) UNSIGNED NOT NULL,
  service_id INT(10) UNSIGNED NOT NULL,
  PRIMARY KEY (servicegroup_id, service_id),
  CONSTRAINT icinga_servicegroup_service_resolved_service
  FOREIGN KEY service (service_id)
  REFERENCES icinga_service (id)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT icinga_servicegroup_service_resolved_servicegroup
  FOREIGN KEY servicegroup (servicegroup_id)
  REFERENCES icinga_servicegroup (id)
    ON DELETE CASCADE
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

INSERT INTO director_schema_migration
  (schema_version, migration_time)
  VALUES (155, NOW());
