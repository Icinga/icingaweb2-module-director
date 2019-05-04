ALTER TABLE icinga_host_template_choice
  ADD COLUMN required_template_id integer DEFAULT NULL,
  ADD COLUMN allowed_roles character varying(255) DEFAULT NULL,
  ADD CONSTRAINT host_template_choice_required_template
  FOREIGN KEY (required_template_id)
  REFERENCES icinga_host (id)
    ON DELETE RESTRICT
    ON UPDATE CASCADE;

ALTER TABLE icinga_service_template_choice
  ADD COLUMN required_template_id integer DEFAULT NULL,
  ADD COLUMN allowed_roles character varying(255) DEFAULT NULL,
  ADD CONSTRAINT service_template_choice_required_template
  FOREIGN KEY (required_template_id)
   REFERENCES icinga_service (id)
     ON DELETE RESTRICT
     ON UPDATE CASCADE;

CREATE INDEX host_template_choice_required_template
  ON icinga_host_template_choice (required_template_id);

CREATE INDEX service_template_choice_required_template
  ON icinga_service_template_choice (required_template_id);

INSERT INTO director_schema_migration
  (schema_version, migration_time)
  VALUES (143, NOW());
