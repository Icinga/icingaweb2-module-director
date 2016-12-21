-- cleanup dangling service_set before we add foreign key
DELETE ss FROM icinga_service_set AS ss
  LEFT JOIN icinga_host AS h ON h.id = ss.host_id
  WHERE ss.object_type = 'object'
        AND ss.host_id IS NOT NULL
        AND h.id IS NULL;

-- cleanup dangling services to service_set
DELETE s FROM icinga_service AS s
  LEFT JOIN icinga_service_set AS ss ON ss.id = s.service_set_id
  WHERE s.object_type IN ('object', 'apply')
        AND s.service_set_id IS NOT NULL
        AND ss.id IS NULL;


ALTER TABLE icinga_service_set
  ADD FOREIGN KEY icinga_service_set_host (host_id)
  REFERENCES icinga_host (id)
  ON DELETE RESTRICT
  ON UPDATE CASCADE;

ALTER TABLE icinga_service
  ADD FOREIGN KEY icinga_service_service_set (service_set_id)
  REFERENCES icinga_service_set (id)
  ON DELETE RESTRICT
  ON UPDATE CASCADE;

INSERT INTO director_schema_migration
(schema_version, migration_time)
VALUES (123, NOW());
