ALTER TABLE icinga_host_template_choice
  ADD COLUMN required_template_id INT(10) UNSIGNED DEFAULT NULL,
  ADD COLUMN allowed_roles VARCHAR(255) DEFAULT NULL,
  ADD CONSTRAINT host_template_choice_required_template
  FOREIGN KEY required_template (required_template_id)
  REFERENCES icinga_host (id)
    ON DELETE RESTRICT
    ON UPDATE CASCADE;

ALTER TABLE icinga_service_template_choice
  ADD COLUMN required_template_id INT(10) UNSIGNED DEFAULT NULL,
  ADD COLUMN allowed_roles VARCHAR(255) DEFAULT NULL,
  ADD CONSTRAINT service_template_choice_required_template
  FOREIGN KEY required_template (required_template_id)
  REFERENCES icinga_service (id)
    ON DELETE RESTRICT
    ON UPDATE CASCADE;

INSERT INTO director_schema_migration
  (schema_version, migration_time)
  VALUES (143, NOW());
