CREATE TABLE director_dictionary (
  id serial,
  dictionary_name character varying(255) NOT NULL,
  PRIMARY KEY (id)
);

CREATE UNIQUE INDEX director_dictionary_unique_dictionary_name ON director_dictionary (dictionary_name);

CREATE TABLE director_dictionaryfield (
  id serial,
  dictionary_id integer NOT NULL,
  datafield_id integer NOT NULL,
  dictionaryfield_name character varying(255) NOT NULL,
  is_required enum_boolean NOT NULL,
  allow_multiple enum_boolean NOT NULL,
  PRIMARY KEY (id),
  CONSTRAINT dictionaryfield_dictionary
    FOREIGN KEY (dictionary_id)
    REFERENCES director_dictionary (id)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT dictionaryfield_datafield
    FOREIGN KEY (datafield_id)
    REFERENCES director_datafield (id)
    ON DELETE RESTRICT
    ON UPDATE CASCADE
);

INSERT INTO director_schema_migration
  (schema_version, migration_time)
  VALUES (118, NOW());
