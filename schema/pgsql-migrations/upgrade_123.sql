-- cleanup dangling service_set before we add foreign key
DELETE FROM icinga_service_set AS ss
  WHERE NOT EXISTS (
      SELECT 1 FROM icinga_host AS h
      WHERE h.id = ss.host_id
  )
  AND object_type = 'object'
  AND host_id IS NOT NULL;

-- cleanup dangling services to service_set
DELETE FROM icinga_service AS s
  WHERE NOT EXISTS (
    SELECT 1 FROM icinga_service_set AS ss
    WHERE ss.id = s.service_set_id
  )
  AND object_type IN ('object', 'apply')
  AND service_set_id IS NOT NULL;


ALTER TABLE icinga_service_set
  ADD CONSTRAINT icinga_service_set_host FOREIGN KEY (host_id)
    REFERENCES icinga_host (id)
    ON DELETE RESTRICT
    ON UPDATE CASCADE;

ALTER TABLE icinga_service
  ADD CONSTRAINT icinga_service_service_set FOREIGN KEY (service_set_id)
    REFERENCES icinga_service_set (id)
    ON DELETE RESTRICT
    ON UPDATE CASCADE;

INSERT INTO director_schema_migration
  (schema_version, migration_time)
  VALUES (123, NOW());
