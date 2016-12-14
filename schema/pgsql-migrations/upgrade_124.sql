ALTER TABLE icinga_service_set
  DROP CONSTRAINT icinga_service_set_host;

ALTER TABLE icinga_service_set
  ADD CONSTRAINT icinga_service_set_host FOREIGN KEY (host_id)
    REFERENCES icinga_host (id)
    ON DELETE CASCADE
    ON UPDATE CASCADE;

ALTER TABLE icinga_service
  DROP CONSTRAINT icinga_service_service_set;

ALTER TABLE icinga_service
  ADD CONSTRAINT icinga_service_service_set FOREIGN KEY (service_set_id)
    REFERENCES icinga_service_set (id)
    ON DELETE CASCADE
    ON UPDATE CASCADE;

INSERT INTO director_schema_migration
  (schema_version, migration_time)
  VALUES (124, NOW());
