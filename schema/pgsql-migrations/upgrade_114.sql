CREATE TABLE icinga_service_set (
  id serial,
  host_id integer NOT NULL,
  object_name character varying(128) NOT NULL,
  object_type enum_object_type_all NOT NULL,
  description text NOT NULL,
  PRIMARY KEY (id)
);

CREATE UNIQUE INDEX service_set_name ON icinga_service_set (object_name, host_id);


CREATE TABLE icinga_service_set_service (
  service_set_id serial,
  service_id serial,
  PRIMARY KEY (service_set_id, service_id),
  CONSTRAINT icinga_service_set_set
    FOREIGN KEY (service_set_id)
    REFERENCES icinga_service_set (id)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT icinga_service_set_service
    FOREIGN KEY (service_id)
    REFERENCES icinga_service (id)
    ON DELETE RESTRICT
    ON UPDATE CASCADE
);


CREATE TABLE icinga_service_set_assignment (
  id serial,
  service_set_id integer NOT NULL,
  filter_string text NOT NULL,
  assign_type enum_assign_type NOT NULL DEFAULT 'assign',
  PRIMARY KEY (id),
  CONSTRAINT icinga_service_set_assignment
    FOREIGN KEY (service_set_id)
    REFERENCES icinga_service_set (id)
    ON DELETE CASCADE
    ON UPDATE CASCADE
);


CREATE TABLE icinga_service_set_var (
  service_set_id integer NOT NULL,
  varname character varying(255) NOT NULL,
  varvalue text DEFAULT NULL,
  format enum_property_format NOT NULL DEFAULT 'string',
  PRIMARY KEY (service_set_id, varname),
  CONSTRAINT icinga_service_set_var_service_set
  FOREIGN KEY (service_set_id)
    REFERENCES icinga_service_set (id)
    ON DELETE CASCADE
    ON UPDATE CASCADE
);

CREATE INDEX service_set_var_service_set ON icinga_service_set_var (service_set_id);
CREATE INDEX service_set_var_search_idx ON icinga_service_set_var (varname);


INSERT INTO director_schema_migration
  (schema_version, migration_time)
  VALUES (114, NOW());
