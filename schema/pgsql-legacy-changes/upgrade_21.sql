DROP TABLE director_datalist_value;

CREATE TABLE director_datalist_entry (
  list_id integer NOT NULL,
  entry_name character varying(255) DEFAULT NULL,
  entry_value text DEFAULT NULL,
  format enum_property_format,
  PRIMARY KEY (list_id, entry_name),
  CONSTRAINT director_datalist_entry_datalist
  FOREIGN KEY (list_id)
    REFERENCES director_datalist (id)
    ON DELETE CASCADE
    ON UPDATE CASCADE
);

CREATE INDEX datalist_entry_datalist ON director_datalist_entry (list_id);

