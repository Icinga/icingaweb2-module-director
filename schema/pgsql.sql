-- TODO:
--
--  - SEE mysql.sql TODOs
--  - FOREIGN KEYS (INDEXES), TIMESTAMPs overview
--  - SET sql_mode = ???
--
-- NOTES:
--
-- INSERT INTO director_activity_log (object_type, object_name, action_name, author, change_time, checksum) VALUES('object', 'foo', 'create', 'alex', CURRENT_TIMESTAMP, decode('cf23df2207d99a74fbe169e3eba035e633b65d94', 'hex'));
--

--
-- Enumerable Types
--
-- TODO: what about translation of the strings?

CREATE TYPE enum_activity_action AS ENUM('create', 'delete', 'modify');
CREATE TYPE enum_boolean AS ENUM('y', 'n');
CREATE TYPE enum_property_format AS ENUM('string', 'expression', 'json');
CREATE TYPE enum_object_type AS ENUM('object', 'template');
CREATE TYPE enum_timeperiod_range_type AS ENUM('include', 'exclude');
CREATE TYPE enum_merge_behaviour AS ENUM('set', 'add', 'substract');
CREATE TYPE enum_command_object_type AS ENUM('object', 'template', 'external_object');
CREATE TYPE enum_apply_object_type AS ENUM('object', 'template', 'apply');
CREATE TYPE enum_state_name AS ENUM('OK', 'Warning', 'Critical', 'Unknown', 'Up', 'Down');
CREATE TYPE enum_type_name AS ENUM('DowntimeStart', 'DowntimeEnd', 'DowntimeRemoved', 'Custom', 'Acknowledgement', 'Problem', 'Recovery', 'FlappingStart', 'FlappingEnd');


CREATE TABLE director_dbversion (
  schema_version INTEGER NOT NULL
);


CREATE TABLE director_activity_log (
  id bigserial,
  object_type character varying(64) NOT NULL,
  object_name character varying(255) NOT NULL,
  action_name enum_activity_action NOT NULL,
  old_properties text DEFAULT NULL,
  new_properties text DEFAULT NULL,
  author character varying(64) NOT NULL,
  change_time timestamp with time zone NOT NULL,
  checksum bytea NOT NULL UNIQUE CHECK(LENGTH(checksum) = 20),
  parent_checksum bytea DEFAULT NULL CHECK(parent_checksum IS NULL OR LENGTH(checksum) = 20),
  PRIMARY KEY (id)
);

CREATE INDEX activity_log_sort_idx ON director_activity_log (change_time);
CREATE INDEX activity_log_search_idx ON director_activity_log (object_name);
CREATE INDEX activity_log_search_idx2 ON director_activity_log (object_type, object_name, change_time);
COMMENT ON COLUMN director_activity_log.old_properties IS 'Property hash, JSON';
COMMENT ON COLUMN director_activity_log.new_properties IS 'Property hash, JSON';


CREATE TABLE director_generated_config (
  checksum bytea CHECK(LENGTH(checksum) = 20),
  director_version character varying(64) DEFAULT NULL,
  director_db_version integer DEFAULT NULL,
  duration integer DEFAULT NULL,
  last_activity_checksum bytea NOT NULL CHECK(LENGTH(last_activity_checksum) = 20),
  PRIMARY KEY (checksum),
  CONSTRAINT director_generated_config_activity
  FOREIGN KEY (last_activity_checksum)
    REFERENCES director_activity_log (checksum)
    ON DELETE RESTRICT
    ON UPDATE RESTRICT
);

CREATE INDEX activity_checksum ON director_generated_config (last_activity_checksum);
COMMENT ON COLUMN director_generated_config.checksum IS 'SHA1(last_activity_checksum;file_path=checksum;file_path=checksum;...)';
COMMENT ON COLUMN director_generated_config.duration IS 'Config generation duration (ms)';


CREATE TABLE director_generated_file (
  checksum bytea CHECK(LENGTH(checksum) = 20),
  content text NOT NULL,
  PRIMARY KEY (checksum)
);

COMMENT ON COLUMN director_generated_file.checksum IS 'SHA1(content)';


CREATE TABLE director_generated_config_file (
  config_checksum bytea CHECK(LENGTH(config_checksum) = 20),
  file_checksum bytea CHECK(LENGTH(file_checksum) = 20),
  file_path character varying(64) NOT NULL,
  PRIMARY KEY (config_checksum, file_path),
  CONSTRAINT director_generated_config_file_config
  FOREIGN KEY (config_checksum)
    REFERENCES director_generated_config (checksum)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT director_generated_config_file_file
  FOREIGN KEY (file_checksum)
    REFERENCES director_generated_file (checksum)
    ON DELETE RESTRICT
    ON UPDATE RESTRICT
);

CREATE INDEX config ON director_generated_config_file (config_checksum);
CREATE INDEX checksum ON director_generated_config_file (file_checksum);
COMMENT ON COLUMN director_generated_config_file.file_path IS 'e.g. zones/nafta/hosts.conf';


CREATE TABLE director_deployment_log (
  id bigserial,
  config_id bigint NOT NULL,
  peer_identity character varying(64) NOT NULL,
  start_time timestamp with time zone NOT NULL,
  end_time timestamp with time zone DEFAULT NULL,
  abort_time timestamp with time zone DEFAULT NULL,
  duration_connection integer DEFAULT NULL,
  duration_dump integer DEFAULT NULL,
  connection_succeeded enum_boolean DEFAULT NULL,
  dump_succeeded enum_boolean DEFAULT NULL,
  startup_succeeded enum_boolean DEFAULT NULL,
  username character varying(64) DEFAULT NULL,
  startup_log text DEFAULT NULL,
  PRIMARY KEY (id)
);

COMMENT ON COLUMN director_deployment_log.duration_connection IS 'The time it took to connect to an Icinga node (ms)';
COMMENT ON COLUMN director_deployment_log.duration_dump IS 'Time spent dumping the config (ms)';
COMMENT ON COLUMN director_deployment_log.username IS 'The user that triggered this deployment';


CREATE TABLE director_datalist (
  id serial,
  list_name character varying(255) NOT NULL,
  owner character varying(255) NOT NULL,
  PRIMARY KEY (id)
);

CREATE UNIQUE INDEX datalist_list_name ON director_datalist (list_name);


CREATE TABLE director_datalist_value (
  list_id integer NOT NULL,
  value_name character varying(255) DEFAULT NULL,
  value_expression text DEFAULT NULL,
  format enum_property_format,
  PRIMARY KEY (list_id, value_name),
  CONSTRAINT director_datalist_value_datalist
  FOREIGN KEY (list_id)
    REFERENCES director_datalist (id)
    ON DELETE CASCADE
    ON UPDATE CASCADE
);

CREATE INDEX datalist_value_datalist ON director_datalist_value (list_id);


CREATE TABLE director_datatype (
  id serial,
  datatype_name character varying(255) NOT NULL,
-- ?? expression VARCHAR(255) NOT NULL,
  PRIMARY KEY (id)
);

CREATE UNIQUE INDEX datatype_name ON director_datatype (datatype_name);


CREATE TABLE icinga_zone (
  id serial,
  parent_zone_id integer DEFAULT NULL,
  object_name character varying(255) NOT NULL UNIQUE,
  object_type enum_object_type NOT NULL,
  is_global enum_boolean NOT NULL DEFAULT 'n',
  PRIMARY KEY (id),
  CONSTRAINT icinga_zone_parent_zone
  FOREIGN KEY (parent_zone_id)
    REFERENCES icinga_zone (id)
    ON DELETE RESTRICT
    ON UPDATE CASCADE
);

CREATE INDEX zone_parent ON icinga_zone (parent_zone_id);


CREATE TABLE icinga_timeperiod (
  id serial,
  object_name character varying(255) NOT NULL,
  display_name character varying(255) DEFAULT NULL,
  update_method character varying(64) DEFAULT NULL,
  zone_id integer DEFAULT NULL,
  object_type enum_object_type NOT NULL,
  PRIMARY KEY (id),
  CONSTRAINT icinga_timeperiod_zone
  FOREIGN KEY (zone_id)
    REFERENCES icinga_zone (id)
    ON DELETE RESTRICT
    ON UPDATE CASCADE
);

CREATE UNIQUE INDEX timeperiod_object_name ON icinga_timeperiod (object_name, zone_id);
CREATE INDEX timeperiod_zone ON icinga_timeperiod (zone_id);
COMMENT ON COLUMN icinga_timeperiod.update_method IS 'Usually LegacyTimePeriod';


CREATE TABLE icinga_timeperiod_range (
  timeperiod_id serial,
  timeperiod_key character varying(255) NOT NULL,
  timeperiod_value character varying(255) NOT NULL,
  range_type enum_timeperiod_range_type NOT NULL DEFAULT 'include',
  merge_behaviour enum_merge_behaviour NOT NULL DEFAULT 'set',
  PRIMARY KEY (timeperiod_id, range_type, timeperiod_key),
  CONSTRAINT icinga_timeperiod_range_timeperiod
  FOREIGN KEY (timeperiod_id)
    REFERENCES icinga_timeperiod (id)
    ON DELETE RESTRICT
    ON UPDATE CASCADE
);

CREATE INDEX timeperiod_range_timeperiod ON icinga_timeperiod_range (timeperiod_id);
COMMENT ON COLUMN icinga_timeperiod_range.timeperiod_key IS 'monday, ...';
COMMENT ON COLUMN icinga_timeperiod_range.timeperiod_value IS '00:00-24:00, ...';
COMMENT ON COLUMN icinga_timeperiod_range.range_type IS 'include -> ranges {}, exclude ranges_ignore {} - not yet';
COMMENT ON COLUMN icinga_timeperiod_range.merge_behaviour IS 'set -> = {}, add -> += {}, substract -> -= {}';


CREATE TABLE icinga_command (
  id serial,
  object_name character varying(255) NOT NULL,
  methods_execute character varying(64) DEFAULT NULL,
  command character varying(255) DEFAULT NULL,
-- env text DEFAULT NULL,
-- vars text DEFAULT NULL,
  timeout smallint DEFAULT NULL,
  zone_id integer DEFAULT NULL,
  object_type enum_command_object_type NOT NULL,
  PRIMARY KEY (id),
  CONSTRAINT icinga_command_zone
  FOREIGN KEY (zone_id)
    REFERENCES icinga_zone (id)
    ON DELETE RESTRICT
    ON UPDATE CASCADE
);

CREATE UNIQUE INDEX command_object_name ON icinga_command (object_name, zone_id);
CREATE INDEX command_zone ON icinga_command (zone_id);
COMMENT ON COLUMN icinga_command.object_type IS 'external_object is an attempt to work with existing commands';


CREATE TABLE icinga_command_argument (
  id serial,
  command_id integer NOT NULL,
  argument_name character varying(64) DEFAULT NULL,
  argument_value text DEFAULT NULL,
  key_string character varying(64) DEFAULT NULL,
  description text DEFAULT NULL,
  skip_key enum_boolean DEFAULT NULL,
  set_if character varying(255) DEFAULT NULL, -- (string expression, must resolve to a numeric value)
  sort_order smallint DEFAULT NULL, -- -> order
  repeat_key enum_boolean DEFAULT NULL,
  value_format enum_property_format NOT NULL DEFAULT 'string',
  set_if_format enum_property_format DEFAULT NULL,
  PRIMARY KEY (id),
  CONSTRAINT icinga_command_argument_command
  FOREIGN KEY (command_id)
    REFERENCES icinga_command (id)
    ON DELETE CASCADE
    ON UPDATE CASCADE
);

CREATE UNIQUE INDEX command_argument_sort_idx ON icinga_command_argument (command_id, sort_order);
CREATE UNIQUE INDEX command_argument_unique_idx ON icinga_command_argument (command_id, argument_name);
CREATE INDEX command_argument_command ON icinga_command_argument (command_id);
COMMENT ON COLUMN icinga_command_argument.argument_name IS '-x, --host';
COMMENT ON COLUMN icinga_command_argument.key_string IS 'Overrides name';
COMMENT ON COLUMN icinga_command_argument.repeat_key IS 'Useful with array values';


CREATE TABLE icinga_command_var (
  command_id integer NOT NULL,
  varname character varying(255) DEFAULT NULL,
  varvalue text DEFAULT NULL,
  format enum_property_format NOT NULL DEFAULT 'string',
  PRIMARY KEY (command_id, varname),
  CONSTRAINT icinga_command_var_command
  FOREIGN KEY (command_id)
    REFERENCES icinga_command (id)
    ON DELETE CASCADE
    ON UPDATE CASCADE
);

CREATE INDEX command_var_command ON icinga_command_var (command_id);


CREATE TABLE icinga_endpoint (
  id serial,
  zone_id integer DEFAULT NULL,
  object_name character varying(255) NOT NULL,
  address character varying(255) DEFAULT NULL,
  port smallint DEFAULT NULL,
  log_duration character varying(32) DEFAULT NULL,
  object_type enum_object_type NOT NULL,
  PRIMARY KEY (id),
  CONSTRAINT icinga_endpoint_zone
  FOREIGN KEY (zone_id)
    REFERENCES icinga_zone (id)
    ON DELETE RESTRICT
    ON UPDATE CASCADE
);

CREATE UNIQUE INDEX endpoint_object_name ON icinga_endpoint (object_name);
CREATE INDEX endpoint_zone ON icinga_endpoint (zone_id);
COMMENT ON COLUMN icinga_endpoint.address IS 'IP address / hostname of remote node';
COMMENT ON COLUMN icinga_endpoint.port IS '5665 if not set';
COMMENT ON COLUMN icinga_endpoint.log_duration IS '1d if not set';


CREATE TABLE icinga_host (
  id serial,
  object_name character varying(255) NOT NULL,
  address character varying(64) DEFAULT NULL,
  address6 character varying(45) DEFAULT NULL,
  check_command_id integer DEFAULT NULL,
  max_check_attempts integer DEFAULT NULL,
  check_period_id integer DEFAULT NULL,
  check_interval character varying(8) DEFAULT NULL,
  retry_interval character varying(8) DEFAULT NULL,
  enable_notifications enum_boolean DEFAULT NULL,
  enable_active_checks enum_boolean DEFAULT NULL,
  enable_passive_checks enum_boolean DEFAULT NULL,
  enable_event_handler enum_boolean DEFAULT NULL,
  enable_flapping enum_boolean DEFAULT NULL,
  enable_perfdata enum_boolean DEFAULT NULL,
  event_command_id integer DEFAULT NULL,
  flapping_threshold smallint default null,
  volatile enum_boolean DEFAULT NULL,
  zone_id integer DEFAULT NULL,
  command_endpoint_id integer DEFAULT NULL,
  notes text DEFAULT NULL,
  notes_url character varying(255) DEFAULT NULL,
  action_url character varying(255) DEFAULT NULL,
  icon_image character varying(255) DEFAULT NULL,
  icon_image_alt character varying(255) DEFAULT NULL,
  object_type enum_object_type NOT NULL,
  PRIMARY KEY (id),
  CONSTRAINT icinga_host_zone
  FOREIGN KEY (zone_id)
    REFERENCES icinga_zone (id)
    ON DELETE RESTRICT
    ON UPDATE CASCADE,
  CONSTRAINT icinga_host_check_period
  FOREIGN KEY (check_period_id)
    REFERENCES icinga_timeperiod (id)
    ON DELETE RESTRICT
    ON UPDATE CASCADE,
  CONSTRAINT icinga_host_check_command
  FOREIGN KEY (check_command_id)
    REFERENCES icinga_command (id)
    ON DELETE RESTRICT
    ON UPDATE CASCADE,
  CONSTRAINT icinga_host_event_command
  FOREIGN KEY (event_command_id)
    REFERENCES icinga_command (id)
    ON DELETE RESTRICT
    ON UPDATE CASCADE,
  CONSTRAINT icinga_host_command_endpoint
  FOREIGN KEY (command_endpoint_id)
    REFERENCES icinga_endpoint (id)
    ON DELETE RESTRICT
    ON UPDATE CASCADE
);

CREATE UNIQUE INDEX object_name_host ON icinga_host (object_name, zone_id);
CREATE INDEX host_zone ON icinga_host (zone_id);
CREATE INDEX host_timeperiod ON icinga_host (check_period_id);
CREATE INDEX host_check_command ON icinga_host (check_command_id);
CREATE INDEX host_event_command ON icinga_host (event_command_id);
CREATE INDEX host_command_endpoint ON icinga_host (command_endpoint_id);


CREATE TABLE icinga_host_inheritance (
  host_id integer NOT NULL,
  parent_host_id integer NOT NULL,
  weight integer DEFAULT NULL,
  PRIMARY KEY (host_id, parent_host_id),
  CONSTRAINT icinga_host_inheritance_host
  FOREIGN KEY (host_id)
    REFERENCES icinga_host (id)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT icinga_host_inheritance_parent_host
  FOREIGN KEY (parent_host_id)
    REFERENCES icinga_host (id)
    ON DELETE RESTRICT
    ON UPDATE CASCADE
);

CREATE UNIQUE INDEX host_inheritance_unique_order ON icinga_host_inheritance (host_id, weight);
CREATE INDEX host_inheritance_host ON icinga_host_inheritance (host_id);
CREATE INDEX host_inheritance_host_parent ON icinga_host_inheritance (parent_host_id);


CREATE TABLE icinga_host_var (
  host_id integer NOT NULL,
  varname character varying(255) DEFAULT NULL,
  varvalue text DEFAULT NULL,
  format enum_property_format, -- immer string vorerst
  PRIMARY KEY (host_id, varname),
  CONSTRAINT icinga_host_var_host
  FOREIGN KEY (host_id)
    REFERENCES icinga_host (id)
    ON DELETE CASCADE
    ON UPDATE CASCADE
);

CREATE INDEX host_var_search_idx ON icinga_host_var (varname);
CREATE INDEX host_var_host ON icinga_host_var (host_id);


CREATE TABLE icinga_service (
  id serial,
  object_name character varying(255) NOT NULL,
  display_name character varying(255) DEFAULT NULL,
  check_command_id integer DEFAULT NULL,
  max_check_attempts integer DEFAULT NULL,
  check_period_id integer DEFAULT NULL,
  check_interval character varying(8) DEFAULT NULL,
  retry_interval character varying(8) DEFAULT NULL,
  enable_notifications enum_boolean DEFAULT NULL,
  enable_active_checks enum_boolean DEFAULT NULL,
  enable_passive_checks enum_boolean DEFAULT NULL,
  enable_event_handler enum_boolean DEFAULT NULL,
  enable_flapping enum_boolean DEFAULT NULL,
  enable_perfdata enum_boolean DEFAULT NULL,
  event_command_id integer DEFAULT NULL,
  flapping_threshold smallint DEFAULT NULL,
  volatile enum_boolean DEFAULT NULL,
  zone_id integer DEFAULT NULL,
  command_endpoint_id integer DEFAULT NULL,
  notes text DEFAULT NULL,
  notes_url character varying(255) DEFAULT NULL,
  action_url character varying(255) DEFAULT NULL,
  icon_image character varying(255) DEFAULT NULL,
  icon_image_alt character varying(255) DEFAULT NULL,
  object_type enum_apply_object_type NOT NULL,
  PRIMARY KEY (id),
-- UNIQUE INDEX object_name (object_name, zone_id),
  CONSTRAINT icinga_service_zone
  FOREIGN KEY (zone_id)
    REFERENCES icinga_zone (id)
    ON DELETE RESTRICT
    ON UPDATE CASCADE,
  CONSTRAINT icinga_service_check_period
  FOREIGN KEY (check_period_id)
    REFERENCES icinga_timeperiod (id)
    ON DELETE RESTRICT
    ON UPDATE CASCADE,
  CONSTRAINT icinga_service_check_command
  FOREIGN KEY (check_command_id)
    REFERENCES icinga_command (id)
    ON DELETE RESTRICT
    ON UPDATE CASCADE,
  CONSTRAINT icinga_service_event_command
  FOREIGN KEY (event_command_id)
    REFERENCES icinga_command (id)
    ON DELETE RESTRICT
    ON UPDATE CASCADE,
  CONSTRAINT icinga_service_command_endpoint
  FOREIGN KEY (command_endpoint_id)
    REFERENCES icinga_endpoint (id)
    ON DELETE RESTRICT
    ON UPDATE CASCADE
);

CREATE INDEX service_zone ON icinga_service (zone_id);
CREATE INDEX service_timeperiod ON icinga_service (check_period_id);
CREATE INDEX service_check_command ON icinga_service (check_command_id);
CREATE INDEX service_event_command ON icinga_service (event_command_id);
CREATE INDEX service_command_endpoint ON icinga_service (command_endpoint_id);


CREATE TABLE icinga_service_var (
  service_id integer NOT NULL,
  varname character varying(255) DEFAULT NULL,
  varvalue text DEFAULT NULL,
  format enum_property_format,
  PRIMARY KEY (service_id, varname),
  CONSTRAINT icinga_service_var_service
  FOREIGN KEY (service_id)
    REFERENCES icinga_service (id)
    ON DELETE CASCADE
    ON UPDATE CASCADE
);

CREATE INDEX service_var_search_idx ON icinga_service_var (varname);
CREATE INDEX service_var_service ON icinga_service_var (service_id);


CREATE TABLE icinga_host_service (
  host_id integer NOT NULL,
  service_id integer NOT NULL,
  PRIMARY KEY (host_id, service_id),
  CONSTRAINT icinga_host_service_host
  FOREIGN KEY (host_id)
    REFERENCES icinga_host (id)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT icinga_host_service_service
  FOREIGN KEY (service_id)
    REFERENCES icinga_service (id)
    ON DELETE CASCADE
    ON UPDATE CASCADE
);

CREATE INDEX host_service_host ON icinga_host_service (host_id);
CREATE INDEX host_service_service ON icinga_host_service (service_id);


CREATE TABLE icinga_hostgroup (
  id serial,
  object_name character varying(255) NOT NULL,
  display_name character varying(255) DEFAULT NULL,
  object_type enum_object_type NOT NULL,
  PRIMARY KEY (id)
);

CREATE UNIQUE INDEX hostgroup_object_name ON icinga_hostgroup (object_name);
CREATE INDEX hostgroup_search_idx ON icinga_hostgroup (display_name);


CREATE TABLE icinga_servicegroup (
  id serial,
  object_name character varying(255) DEFAULT NULL,
  display_name character varying(255) DEFAULT NULL,
  object_type enum_object_type NOT NULL,
  PRIMARY KEY (id)
);

CREATE UNIQUE INDEX servicegroup_object_name ON icinga_servicegroup (object_name);
CREATE INDEX servicegroup_search_idx ON icinga_servicegroup (display_name);


CREATE TABLE icinga_servicegroup_service (
  servicegroup_id integer NOT NULL,
  service_id integer NOT NULL,
  PRIMARY KEY (servicegroup_id, service_id),
  CONSTRAINT icinga_servicegroup_service_service
  FOREIGN KEY (service_id)
    REFERENCES icinga_service (id)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT icinga_servicegroup_service_servicegroup
  FOREIGN KEY (servicegroup_id)
    REFERENCES icinga_servicegroup (id)
    ON DELETE CASCADE
    ON UPDATE CASCADE
);

CREATE INDEX servicegroup_service_service ON icinga_servicegroup_service (service_id);
CREATE INDEX servicegroup_service_servicegroup ON icinga_servicegroup_service (servicegroup_id);


CREATE TABLE icinga_hostgroup_host (
  hostgroup_id integer NOT NULL,
  host_id integer NOT NULL,
  PRIMARY KEY (hostgroup_id, host_id),
  CONSTRAINT icinga_hostgroup_host_host
  FOREIGN KEY (host_id)
    REFERENCES icinga_host (id)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT icinga_hostgroup_host_hostgroup
  FOREIGN KEY (hostgroup_id)
    REFERENCES icinga_hostgroup (id)
    ON DELETE CASCADE
    ON UPDATE CASCADE
);

CREATE INDEX hostgroup_host_host ON icinga_hostgroup_host (host_id);
CREATE INDEX hostgroup_host_hostgroup ON icinga_hostgroup_host (hostgroup_id);


CREATE TABLE icinga_hostgroup_parent (
  hostgroup_id integer NOT NULL,
  parent_hostgroup_id integer NOT NULL,
  PRIMARY KEY (hostgroup_id, parent_hostgroup_id),
  CONSTRAINT icinga_hostgroup_parent_hostgroup
  FOREIGN KEY (hostgroup_id)
    REFERENCES icinga_hostgroup (id)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT icinga_hostgroup_parent_parent
  FOREIGN KEY (parent_hostgroup_id)
    REFERENCES icinga_hostgroup (id)
    ON DELETE RESTRICT
    ON UPDATE CASCADE
);

CREATE INDEX hostgroup_parent_hostgroup ON icinga_hostgroup_parent (hostgroup_id);
CREATE INDEX hostgroup_parent_parent ON icinga_hostgroup_parent (parent_hostgroup_id);


CREATE TABLE icinga_user (
  id serial,
  object_name character varying(255) DEFAULT NULL,
  display_name character varying(255) DEFAULT NULL,
  email character varying(255) DEFAULT NULL,
  pager character varying(255) DEFAULT NULL,
  enable_notifications enum_boolean DEFAULT NULL,
  period_id integer DEFAULT NULL,
  zone_id integer DEFAULT NULL,
  object_type enum_object_type NOT NULL,
  PRIMARY KEY (id),
  CONSTRAINT icinga_user_zone
  FOREIGN KEY (zone_id)
    REFERENCES icinga_zone (id)
    ON DELETE RESTRICT
    ON UPDATE CASCADE
);

CREATE UNIQUE INDEX user_object_name ON icinga_user (object_name, zone_id);
CREATE INDEX user_zone ON icinga_user (zone_id);


CREATE TABLE icinga_user_filter_state (
  user_id integer NOT NULL,
  state_name enum_state_name NOT NULL,
  merge_behaviour enum_merge_behaviour NOT NULL DEFAULT 'set',
  PRIMARY KEY (user_id, state_name),
  CONSTRAINT icinga_user_filter_state_user
  FOREIGN KEY (user_id)
    REFERENCES icinga_user (id)
    ON DELETE CASCADE
    ON UPDATE CASCADE
);

CREATE INDEX user_filter_state_user ON icinga_user_filter_state (user_id);
COMMENT ON COLUMN icinga_user_filter_state.merge_behaviour IS 'set: = [], add: += [], substract: -= []';


CREATE TABLE icinga_user_filter_type (
  user_id integer NOT NULL,
  type_name enum_type_name NOT NULL,
  merge_behaviour enum_merge_behaviour NOT NULL DEFAULT 'set',
  PRIMARY KEY (user_id, type_name),
  CONSTRAINT icinga_user_filter_type_user
  FOREIGN KEY (user_id)
    REFERENCES icinga_user (id)
    ON DELETE CASCADE
    ON UPDATE CASCADE
);

CREATE INDEX user_filter_type_user ON icinga_user_filter_type (user_id);
COMMENT ON COLUMN icinga_user_filter_type.merge_behaviour IS 'set: = [], add: += [], substract: -= []';


CREATE TABLE icinga_user_var (
  user_id integer NOT NULL,
  varname character varying(255) DEFAULT NULL,
  varvalue text DEFAULT NULL,
  format enum_property_format NOT NULL DEFAULT 'string',
  PRIMARY KEY (user_id, varname),
  CONSTRAINT icinga_user_var_user
  FOREIGN KEY (user_id)
    REFERENCES icinga_user (id)
    ON DELETE CASCADE
    ON UPDATE CASCADE
);

CREATE INDEX user_var_search_idx ON icinga_user_var (varname);
CREATE INDEX user_var_user ON icinga_user_var (user_id);


CREATE TABLE icinga_usergroup (
  id serial,
  object_name character varying(255) NOT NULL,
  display_name character varying(255) DEFAULT NULL,
  zone_id integer DEFAULT NULL,
  object_type enum_object_type NOT NULL,
  PRIMARY KEY (id)
);

CREATE UNIQUE INDEX usergroup_search_idx ON icinga_usergroup (display_name);
CREATE INDEX usergroup_object_name ON icinga_usergroup (object_name, zone_id);


CREATE TABLE icinga_usergroup_user (
  usergroup_id integer NOT NULL,
  user_id integer NOT NULL,
  PRIMARY KEY (usergroup_id, user_id),
  CONSTRAINT icinga_usergroup_user_user
  FOREIGN KEY (user_id)
    REFERENCES icinga_user (id)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT icinga_usergroup_user_usergroup
  FOREIGN KEY (usergroup_id)
    REFERENCES icinga_usergroup (id)
    ON DELETE CASCADE
    ON UPDATE CASCADE
);

CREATE INDEX usergroup_user_user ON icinga_usergroup_user (user_id);
CREATE INDEX usergroup_user_usergroup ON icinga_usergroup_user (usergroup_id);


CREATE TABLE icinga_usergroup_parent (
  usergroup_id integer NOT NULL,
  parent_usergroup_id integer NOT NULL,
  PRIMARY KEY (usergroup_id, parent_usergroup_id),
  CONSTRAINT icinga_usergroup_parent_usergroup
  FOREIGN KEY (usergroup_id)
    REFERENCES icinga_usergroup (id)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT icinga_usergroup_parent_parent
  FOREIGN KEY (parent_usergroup_id)
    REFERENCES icinga_usergroup (id)
    ON DELETE RESTRICT
    ON UPDATE CASCADE
);

CREATE INDEX usergroup_parent_usergroup ON icinga_usergroup_parent (usergroup_id);
CREATE INDEX usergroup_parent_parent ON icinga_usergroup_parent (parent_usergroup_id);

--
-- TODO: unfinished: see mysql.sql schema from sync_*
--
