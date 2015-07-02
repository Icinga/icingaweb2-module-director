CREATE TABLE icinga_host_field (
  host_id integer NOT NULL,
  fieldname character varying(64) NOT NULL,
  caption character varying(255) NOT NULL,
  datatype_id integer NOT NULL,
-- datatype_param? multiple ones?
  default_value text DEFAULT NULL,
  format enum_property_format,
  PRIMARY KEY (host_id, fieldname),
  CONSTRAINT icinga_host_field_host
  FOREIGN KEY (host_id)
  REFERENCES icinga_host (id)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT icinga_host_field_datatype
  FOREIGN KEY (datatype_id)
  REFERENCES director_datatype (id)
    ON DELETE RESTRICT
    ON UPDATE CASCADE
);

CREATE UNIQUE INDEX host_field_key ON icinga_host_field (host_id, fieldname);
CREATE INDEX host_field_search_idx ON icinga_host_field (fieldname);
CREATE INDEX host_field_host ON icinga_host_field (host_id);
CREATE INDEX host_field_datatype ON icinga_host_field (datatype);

COMMENT ON COLUMN icinga_host_field.host_id IS 'Makes only sense for templates';


CREATE TABLE icinga_service_field (
  service_id integer NOT NULL,
  fieldname character varying(64) NOT NULL,
  caption character varying(255) NOT NULL,
  datatype_id integer NOT NULL,
-- datatype_param? multiple ones?
  default_value text DEFAULT NULL,
  format enum_property_format,
  PRIMARY KEY (service_id, fieldname),
  CONSTRAINT icinga_service_field_service
  FOREIGN KEY (service_id)
  REFERENCES icinga_service (id)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT icinga_service_field_datatype
  FOREIGN KEY datatype (datatype_id)
  REFERENCES director_datatype (id)
    ON DELETE RESTRICT
    ON UPDATE CASCADE
);

CREATE UNIQUE INDEX service_field_key ON icinga_service_field (service_id, fieldname);
CREATE INDEX service_field_search_idx ON icinga_service_field (fieldname);
CREATE INDEX service_field_service ON icinga_service_field (service_id);
CREATE INDEX service_field_datatype ON icinga_service_field (datatype);
COMMENT ON COLUMN icinga_service_field.service_id IS 'Makes only sense for templates';
