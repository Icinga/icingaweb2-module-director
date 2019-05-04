CREATE TABLE icinga_host_template_choice (
  id INT(10) UNSIGNED AUTO_INCREMENT NOT NULL,
  object_name VARCHAR(64) NOT NULL,
  description TEXT DEFAULT NULL,
  min_required SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  max_allowed SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  PRIMARY KEY (id),
  UNIQUE KEY (object_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

ALTER TABLE icinga_host
  ADD COLUMN template_choice_id INT(10) UNSIGNED DEFAULT NULL,
  ADD CONSTRAINT icinga_host_template_choice
  FOREIGN KEY choice (template_choice_id)
  REFERENCES icinga_host_template_choice (id)
    ON DELETE RESTRICT
    ON UPDATE CASCADE;

INSERT INTO director_schema_migration
(schema_version, migration_time)
VALUES (132, NOW());
