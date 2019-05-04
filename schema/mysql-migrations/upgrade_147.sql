CREATE TABLE icinga_host_service_blacklist (
  host_id INT(10) UNSIGNED NOT NULL,
  service_id INT(10) UNSIGNED NOT NULL,
  PRIMARY KEY (host_id, service_id),
  CONSTRAINT icinga_host_service_bl_host
  FOREIGN KEY host (host_id)
  REFERENCES icinga_host (id)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT icinga_host_service_bl_service
  FOREIGN KEY service (service_id)
  REFERENCES icinga_service (id)
    ON DELETE CASCADE
    ON UPDATE CASCADE
) ENGINE=InnoDB;


INSERT INTO director_schema_migration
  (schema_version, migration_time)
  VALUES (147, NOW());
