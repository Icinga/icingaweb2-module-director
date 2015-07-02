DROP TABLE director_datatype;

CREATE TABLE director_datafield (
  id serial,
  varname character varying(255) NOT NULL,
  caption character varying(255) NOT NULL,
  description text DEFAULT NULL,
  datatype character varying(255) NOT NULL,
-- datatype_param? multiple ones?
  format enum_property_format,
  PRIMARY KEY (id)
);

DROP TABLE icinga_host_field;

CREATE TABLE icinga_host_field (
  host_id integer NOT NULL,
  datafield_id integer NOT NULL,
  is_required enum_boolean DEFAULT NULL,
  PRIMARY KEY (host_id, datafield_id),
  CONSTRAINT icinga_host_field_host
  FOREIGN KEY (host_id)
    REFERENCES icinga_host (id)
  ON DELETE CASCADE
  ON UPDATE CASCADE,
  CONSTRAINT icinga_host_field_datafield
  FOREIGN KEY (datafield_id)
    REFERENCES director_datafield (id)
  ON DELETE CASCADE
  ON UPDATE CASCADE
);

CREATE UNIQUE INDEX host_field_key ON icinga_host_field (host_id, datafield_id);
CREATE INDEX host_field_host ON icinga_host_field (host_id);
CREATE INDEX host_field_datafield ON icinga_host_field (datafield_id);
COMMENT ON COLUMN icinga_host_field.host_id IS 'Makes only sense for templates';

DROP TABLE icinga_service_field;

CREATE TABLE icinga_service_field (
  service_id integer NOT NULL,
  datafield_id integer NOT NULL,
  is_required enum_boolean DEFAULT NULL,
  PRIMARY KEY (service_id, datafield_id),
  CONSTRAINT icinga_service_field_service
  FOREIGN KEY (service_id)
    REFERENCES icinga_service (id)
  ON DELETE CASCADE
  ON UPDATE CASCADE,
  CONSTRAINT icinga_service_field_datafield
  FOREIGN KEY (datafield_id)
    REFERENCES director_datafield (id)
  ON DELETE CASCADE
  ON UPDATE CASCADE
);

CREATE UNIQUE INDEX service_field_key ON icinga_service_field (service_id, datafield_id);
CREATE INDEX service_field_service ON icinga_service_field (service_id);
CREATE INDEX service_field_datafield ON icinga_service_field (datafield_id);
COMMENT ON COLUMN icinga_service_field.service_id IS 'Makes only sense for templates';
