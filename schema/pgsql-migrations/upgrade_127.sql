ALTER TABLE icinga_command_var
  ADD COLUMN checksum bytea DEFAULT NULL CHECK(LENGTH(checksum) = 20);
CREATE INDEX command_var_search_idx ON icinga_command_var (varname);
CREATE INDEX command_var_checksum ON icinga_command_var (checksum);


ALTER TABLE icinga_host_var
  ADD COLUMN checksum bytea DEFAULT NULL CHECK(LENGTH(checksum) = 20);
CREATE INDEX host_var_checksum ON icinga_host_var (checksum);


ALTER TABLE icinga_notification_var
  ADD COLUMN checksum bytea DEFAULT NULL CHECK(LENGTH(checksum) = 20);
CREATE INDEX notification_var_command ON icinga_notification_var (notification_id);
CREATE INDEX notification_var_checksum ON icinga_notification_var (checksum);


ALTER TABLE icinga_service_set_var
  ADD COLUMN checksum bytea DEFAULT NULL CHECK(LENGTH(checksum) = 20);
CREATE INDEX service_set_var_checksum ON icinga_service_set_var (checksum);


ALTER TABLE icinga_service_var
  ADD COLUMN checksum bytea DEFAULT NULL CHECK(LENGTH(checksum) = 20);
CREATE INDEX service_var_checksum ON icinga_service_var (checksum);


ALTER TABLE icinga_user_var
  ADD COLUMN checksum bytea DEFAULT NULL CHECK(LENGTH(checksum) = 20);
CREATE INDEX user_var_checksum ON icinga_user_var (checksum);


CREATE TABLE icinga_var (
  checksum bytea NOT NULL CHECK(LENGTH(checksum) = 20),
  rendered_checksum bytea NOT NULL CHECK(LENGTH(checksum) = 20),
  varname character varying(255) NOT NULL,
  varvalue TEXT NOT NULL,
  rendered TEXT NOT NULL,
  PRIMARY KEY (checksum)
);

CREATE INDEX var_search_idx ON icinga_var (varname);


CREATE TABLE icinga_flat_var (
  var_checksum bytea NOT NULL CHECK(LENGTH(var_checksum) = 20),
  flatname_checksum bytea NOT NULL CHECK(LENGTH(flatname_checksum) = 20),
  flatname character varying(512) NOT NULL,
  flatvalue TEXT NOT NULL,
  PRIMARY KEY (var_checksum, flatname_checksum),
  CONSTRAINT flat_var_var
    FOREIGN KEY (var_checksum)
    REFERENCES icinga_var (checksum)
    ON DELETE CASCADE
    ON UPDATE CASCADE
);

CREATE INDEX flat_var_var_checksum ON icinga_flat_var (var_checksum);
CREATE INDEX flat_var_search_varname ON icinga_flat_var (flatname);
CREATE INDEX flat_var_search_varvalue ON icinga_flat_var (flatvalue);


CREATE TABLE icinga_command_resolved_var (
  command_id integer NOT NULL,
  varname character varying(255) NOT NULL,
  checksum bytea NOT NULL CHECK(LENGTH(checksum) = 20),
  PRIMARY KEY (command_id, checksum),
  CONSTRAINT command_resolved_var_command
    FOREIGN KEY (command_id)
    REFERENCES icinga_command (id)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT command_resolved_var_checksum
    FOREIGN KEY (checksum)
    REFERENCES icinga_var (checksum)
    ON DELETE RESTRICT
    ON UPDATE RESTRICT
);

CREATE INDEX command_resolved_var_search_varname ON icinga_command_resolved_var (varname);
CREATE INDEX command_resolved_var_command_id ON icinga_command_resolved_var (command_id);
CREATE INDEX command_resolved_var_schecksum ON icinga_command_resolved_var (checksum);


CREATE TABLE icinga_host_resolved_var (
  host_id integer NOT NULL,
  varname character varying(255) NOT NULL,
  checksum bytea NOT NULL CHECK(LENGTH(checksum) = 20),
  PRIMARY KEY (host_id, checksum),
  CONSTRAINT host_resolved_var_host
  FOREIGN KEY (host_id)
  REFERENCES icinga_host (id)
  ON DELETE CASCADE
  ON UPDATE CASCADE,
  CONSTRAINT host_resolved_var_checksum
  FOREIGN KEY (checksum)
  REFERENCES icinga_var (checksum)
  ON DELETE RESTRICT
  ON UPDATE RESTRICT
);

CREATE INDEX host_resolved_var_search_varname ON icinga_host_resolved_var (varname);
CREATE INDEX host_resolved_var_host_id ON icinga_host_resolved_var (host_id);
CREATE INDEX host_resolved_var_schecksum ON icinga_host_resolved_var (checksum);


CREATE TABLE icinga_notification_resolved_var (
  notification_id integer NOT NULL,
  varname character varying(255) NOT NULL,
  checksum bytea NOT NULL CHECK(LENGTH(checksum) = 20),
  PRIMARY KEY (notification_id, checksum),
  CONSTRAINT notification_resolved_var_notification
  FOREIGN KEY (notification_id)
  REFERENCES icinga_notification (id)
  ON DELETE CASCADE
  ON UPDATE CASCADE,
  CONSTRAINT notification_resolved_var_checksum
  FOREIGN KEY (checksum)
  REFERENCES icinga_var (checksum)
  ON DELETE RESTRICT
  ON UPDATE RESTRICT
);

CREATE INDEX notification_resolved_var_search_varname ON icinga_notification_resolved_var (varname);
CREATE INDEX notification_resolved_var_notification_id ON icinga_notification_resolved_var (notification_id);
CREATE INDEX notification_resolved_var_schecksum ON icinga_notification_resolved_var (checksum);


CREATE TABLE icinga_service_set_resolved_var (
  service_set_id integer NOT NULL,
  varname character varying(255) NOT NULL,
  checksum bytea NOT NULL CHECK(LENGTH(checksum) = 20),
  PRIMARY KEY (service_set_id, checksum),
  CONSTRAINT service_set_resolved_var_service_set
  FOREIGN KEY (service_set_id)
  REFERENCES icinga_service_set (id)
  ON DELETE CASCADE
  ON UPDATE CASCADE,
  CONSTRAINT service_set_resolved_var_checksum
  FOREIGN KEY (checksum)
  REFERENCES icinga_var (checksum)
  ON DELETE RESTRICT
  ON UPDATE RESTRICT
);

CREATE INDEX service_set_resolved_var_search_varname ON icinga_service_set_resolved_var (varname);
CREATE INDEX service_set_resolved_var_service_set_id ON icinga_service_set_resolved_var (service_set_id);
CREATE INDEX service_set_resolved_var_schecksum ON icinga_service_set_resolved_var (checksum);


CREATE TABLE icinga_service_resolved_var (
  service_id integer NOT NULL,
  varname character varying(255) NOT NULL,
  checksum bytea NOT NULL CHECK(LENGTH(checksum) = 20),
  PRIMARY KEY (service_id, checksum),
  CONSTRAINT service_resolved_var_service
  FOREIGN KEY (service_id)
  REFERENCES icinga_service (id)
  ON DELETE CASCADE
  ON UPDATE CASCADE,
  CONSTRAINT service_resolved_var_checksum
  FOREIGN KEY (checksum)
  REFERENCES icinga_var (checksum)
  ON DELETE RESTRICT
  ON UPDATE RESTRICT
);

CREATE INDEX service_resolved_var_search_varname ON icinga_service_resolved_var (varname);
CREATE INDEX service_resolved_var_service_id ON icinga_service_resolved_var (service_id);
CREATE INDEX service_resolved_var_schecksum ON icinga_service_resolved_var (checksum);


CREATE TABLE icinga_user_resolved_var (
  user_id integer NOT NULL,
  varname character varying(255) NOT NULL,
  checksum bytea NOT NULL CHECK(LENGTH(checksum) = 20),
  PRIMARY KEY (user_id, checksum),
  CONSTRAINT user_resolved_var_user
  FOREIGN KEY (user_id)
  REFERENCES icinga_user (id)
  ON DELETE CASCADE
  ON UPDATE CASCADE,
  CONSTRAINT user_resolved_var_checksum
  FOREIGN KEY (checksum)
  REFERENCES icinga_var (checksum)
  ON DELETE RESTRICT
  ON UPDATE RESTRICT
);

CREATE INDEX user_resolved_var_search_varname ON icinga_user_resolved_var (varname);
CREATE INDEX user_resolved_var_user_id ON icinga_user_resolved_var (user_id);
CREATE INDEX user_resolved_var_schecksum ON icinga_user_resolved_var (checksum);


INSERT INTO director_schema_migration
  (schema_version, migration_time)
  VALUES (127, NOW());
