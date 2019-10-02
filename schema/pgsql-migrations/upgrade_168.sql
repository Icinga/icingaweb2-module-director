
CREATE TABLE director_datafield_category (
  id serial,
  category_name character varying(255) NOT NULL,
  description text DEFAULT NULL,
  PRIMARY KEY (id)
);

CREATE UNIQUE INDEX datafield_category_name ON director_datafield_category (category_name);


ALTER TABLE director_datafield
  ADD COLUMN category_id integer DEFAULT NULL,
  ADD CONSTRAINT director_datafield_category
    FOREIGN KEY (category_id)
    REFERENCES director_datafield_category (id)
    ON DELETE RESTRICT
    ON UPDATE CASCADE;

CREATE INDEX datafield_category ON director_datafield (category_id);


INSERT INTO director_schema_migration
  (schema_version, migration_time)
  VALUES (168, NOW());
