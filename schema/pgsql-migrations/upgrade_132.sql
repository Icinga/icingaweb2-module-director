CREATE TABLE icinga_host_template_choice (
  id serial,
  object_name character varying(64) NOT NULL,
  description text DEFAULT NULL,
  min_required smallint NOT NULL DEFAULT 0,
  max_allowed smallint NOT NULL DEFAULT 1,
  PRIMARY KEY (id)
);

CREATE UNIQUE INDEX host_template_choice_object_name ON icinga_host_template_choice (object_name);


ALTER TABLE icinga_host
  ADD COLUMN template_choice_id int DEFAULT NULL,
  ADD CONSTRAINT icinga_host_template_choice
  FOREIGN KEY (template_choice_id)
  REFERENCES icinga_host_template_choice (id)
    ON DELETE RESTRICT
    ON UPDATE CASCADE;

CREATE INDEX host_template_choice ON icinga_host (template_choice_id);

INSERT INTO director_schema_migration
  (schema_version, migration_time)
  VALUES (132, NOW());
