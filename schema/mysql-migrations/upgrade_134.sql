ALTER TABLE icinga_host
  DROP FOREIGN KEY icinga_host_template_choice,
  ADD CONSTRAINT icinga_host_template_choice_v2
  FOREIGN KEY template_choice (template_choice_id)
  REFERENCES icinga_host_template_choice (id)
    ON DELETE SET NULL
    ON UPDATE CASCADE;

ALTER TABLE icinga_service
  DROP FOREIGN KEY icinga_service_template_choice,
  ADD CONSTRAINT icinga_service_template_choice_v2
  FOREIGN KEY template_choice (template_choice_id)
  REFERENCES icinga_service_template_choice (id)
    ON DELETE SET NULL
    ON UPDATE CASCADE;

INSERT INTO director_schema_migration
  (schema_version, migration_time)
  VALUES (134, NOW());
