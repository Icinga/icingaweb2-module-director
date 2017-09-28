--
-- PostgreSQL schema
-- =================
--
-- You should normally not be required to care about schema handling.
-- Director does all the migrations for you and guides you either in
-- the frontend or provides everything you need for automated migration
-- handling. Please find more related information in our documentation.

CREATE TYPE enum_activity_action AS ENUM('create', 'delete', 'modify');
CREATE TYPE enum_boolean AS ENUM('y', 'n');
CREATE TYPE enum_property_format AS ENUM('string', 'expression', 'json');
CREATE TYPE enum_object_type_all AS ENUM('object', 'template', 'apply', 'external_object'); -- TODO: can we check for an invalid
CREATE TYPE enum_object_type AS ENUM('object', 'template', 'external_object');
CREATE TYPE enum_timeperiod_range_type AS ENUM('include', 'exclude');
CREATE TYPE enum_merge_behaviour AS ENUM('set', 'add', 'substract', 'override');
CREATE TYPE enum_set_merge_behaviour AS ENUM('override', 'extend', 'blacklist');
CREATE TYPE enum_command_object_type AS ENUM('object', 'template', 'external_object');
CREATE TYPE enum_apply_object_type AS ENUM('object', 'template', 'apply', 'external_object');
CREATE TYPE enum_state_name AS ENUM('OK', 'Warning', 'Critical', 'Unknown', 'Up', 'Down');
CREATE TYPE enum_type_name AS ENUM('DowntimeStart', 'DowntimeEnd', 'DowntimeRemoved', 'Custom', 'Acknowledgement', 'Problem', 'Recovery', 'FlappingStart', 'FlappingEnd');
CREATE TYPE enum_sync_rule_object_type AS ENUM(
  'host',
  'service',
  'command',
  'user',
  'hostgroup',
  'servicegroup',
  'usergroup',
  'datalistEntry',
  'endpoint',
  'zone',
  'timePeriod',
  'serviceSet'
);
CREATE TYPE enum_sync_rule_update_policy AS ENUM('merge', 'override', 'ignore');
CREATE TYPE enum_sync_property_merge_policy AS ENUM('override', 'merge');
CREATE TYPE enum_sync_state AS ENUM(
    'unknown',
    'in-sync',
    'pending-changes',
    'failing'
);
CREATE TYPE enum_host_service AS ENUM('host', 'service');


CREATE OR REPLACE FUNCTION unix_timestamp(timestamp with time zone) RETURNS bigint AS '
        SELECT EXTRACT(EPOCH FROM $1)::bigint AS result
' LANGUAGE sql;


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
CREATE INDEX activity_log_author ON director_activity_log (author);
COMMENT ON COLUMN director_activity_log.old_properties IS 'Property hash, JSON';
COMMENT ON COLUMN director_activity_log.new_properties IS 'Property hash, JSON';


CREATE TABLE director_generated_config (
  checksum bytea CHECK(LENGTH(checksum) = 20),
  director_version character varying(64) DEFAULT NULL,
  director_db_version integer DEFAULT NULL,
  duration integer DEFAULT NULL,
  first_activity_checksum bytea NOT NULL CHECK(LENGTH(first_activity_checksum) = 20),
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
  content text DEFAULT NULL,
  cnt_object SMALLINT NOT NULL DEFAULT 0,
  cnt_template SMALLINT NOT NULL DEFAULT 0,
  cnt_apply SMALLINT NOT NULL DEFAULT 0,
  PRIMARY KEY (checksum)
);

COMMENT ON COLUMN director_generated_file.checksum IS 'SHA1(content)';


CREATE TABLE director_generated_config_file (
  config_checksum bytea CHECK(LENGTH(config_checksum) = 20),
  file_checksum bytea CHECK(LENGTH(file_checksum) = 20),
  file_path character varying(128) NOT NULL,
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
  config_checksum bytea CHECK(LENGTH(config_checksum) = 20),
  last_activity_checksum bytea CHECK(LENGTH(config_checksum) = 20),
  peer_identity character varying(64) NOT NULL,
  start_time timestamp with time zone NOT NULL,
  end_time timestamp with time zone DEFAULT NULL,
  abort_time timestamp with time zone DEFAULT NULL,
  duration_connection integer DEFAULT NULL,
  duration_dump integer DEFAULT NULL,
  stage_name CHARACTER VARYING(96),
  stage_collected enum_boolean DEFAULT NULL,
  connection_succeeded enum_boolean DEFAULT NULL,
  dump_succeeded enum_boolean DEFAULT NULL,
  startup_succeeded enum_boolean DEFAULT NULL,
  username character varying(64) DEFAULT NULL,
  startup_log text DEFAULT NULL,
  PRIMARY KEY (id),
  CONSTRAINT config_checksum
    FOREIGN KEY (config_checksum)
    REFERENCES director_generated_config (checksum)
    ON DELETE SET NULL
    ON UPDATE RESTRICT
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


CREATE TABLE director_datalist_entry (
  list_id integer NOT NULL,
  entry_name character varying(255) NOT NULL,
  entry_value text DEFAULT NULL,
  format enum_property_format,
  allowed_roles character varying(255) DEFAULT NULL,
  PRIMARY KEY (list_id, entry_name),
  CONSTRAINT director_datalist_entry_datalist
  FOREIGN KEY (list_id)
    REFERENCES director_datalist (id)
    ON DELETE CASCADE
    ON UPDATE CASCADE
);

CREATE INDEX datalist_entry_datalist ON director_datalist_entry (list_id);


CREATE TABLE director_datafield (
  id serial,
  varname character varying(64) NOT NULL,
  caption character varying(255) NOT NULL,
  description text DEFAULT NULL,
  datatype character varying(255) NOT NULL,
-- datatype_param? multiple ones?
  format enum_property_format,
  PRIMARY KEY (id)
);

CREATE INDEX search_idx ON director_datafield (varname);


CREATE TABLE director_datafield_setting (
  datafield_id integer NOT NULL,
  setting_name character varying(64) NOT NULL,
  setting_value text NOT NULL,
  PRIMARY KEY (datafield_id, setting_name),
  CONSTRAINT datafield_id_settings
  FOREIGN KEY (datafield_id)
  REFERENCES director_datafield (id)
  ON DELETE CASCADE
  ON UPDATE CASCADE
);

CREATE INDEX director_datafield_datafield ON director_datafield_setting (datafield_id);


CREATE TABLE director_schema_migration (
  schema_version SMALLINT NOT NULL,
  migration_time TIMESTAMP WITH TIME ZONE NOT NULL,
  PRIMARY KEY(schema_version)
);


CREATE TABLE director_setting (
  setting_name character varying(64) NOT NULL,
  setting_value character varying(255) NOT NULL,
  PRIMARY KEY(setting_name)
);


CREATE TABLE icinga_zone (
  id serial,
  parent_id integer DEFAULT NULL,
  object_name character varying(255) NOT NULL UNIQUE,
  object_type enum_object_type_all NOT NULL,
  disabled enum_boolean NOT NULL DEFAULT 'n',
  is_global enum_boolean NOT NULL DEFAULT 'n',
  PRIMARY KEY (id),
  CONSTRAINT icinga_zone_parent_zone
  FOREIGN KEY (parent_id)
    REFERENCES icinga_zone (id)
    ON DELETE RESTRICT
    ON UPDATE CASCADE
);

CREATE INDEX zone_parent ON icinga_zone (parent_id);


CREATE TABLE icinga_zone_inheritance (
  zone_id integer NOT NULL,
  parent_zone_id integer NOT NULL,
  weight integer DEFAULT NULL,
  PRIMARY KEY (zone_id, parent_zone_id),
  CONSTRAINT icinga_zone_inheritance_zone
  FOREIGN KEY (zone_id)
  REFERENCES icinga_zone (id)
  ON DELETE CASCADE
  ON UPDATE CASCADE,
  CONSTRAINT icinga_zone_inheritance_parent_zone
  FOREIGN KEY (parent_zone_id)
  REFERENCES icinga_zone (id)
  ON DELETE RESTRICT
  ON UPDATE CASCADE
);

CREATE UNIQUE INDEX zone_inheritance_unique_order ON icinga_zone_inheritance (zone_id, weight);
CREATE INDEX zone_inheritance_zone ON icinga_zone_inheritance (zone_id);
CREATE INDEX zone_inheritance_zone_parent ON icinga_zone_inheritance (parent_zone_id);


CREATE TABLE icinga_timeperiod (
  id serial,
  object_name character varying(255) NOT NULL,
  display_name character varying(255) DEFAULT NULL,
  update_method character varying(64) DEFAULT NULL,
  zone_id integer DEFAULT NULL,
  object_type enum_object_type_all NOT NULL,
  disabled enum_boolean NOT NULL DEFAULT 'n',
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


CREATE TABLE icinga_timeperiod_inheritance (
  timeperiod_id integer NOT NULL,
  parent_timeperiod_id integer NOT NULL,
  weight integer DEFAULT NULL,
  PRIMARY KEY (timeperiod_id, parent_timeperiod_id),
  CONSTRAINT icinga_timeperiod_inheritance_timeperiod
  FOREIGN KEY (timeperiod_id)
  REFERENCES icinga_timeperiod (id)
  ON DELETE CASCADE
  ON UPDATE CASCADE,
  CONSTRAINT icinga_timeperiod_inheritance_parent_timeperiod
  FOREIGN KEY (parent_timeperiod_id)
  REFERENCES icinga_timeperiod (id)
  ON DELETE RESTRICT
  ON UPDATE CASCADE
);

CREATE UNIQUE INDEX timeperiod_inheritance_unique_order ON icinga_timeperiod_inheritance (timeperiod_id, weight);
CREATE INDEX timeperiod_inheritance_timeperiod ON icinga_timeperiod_inheritance (timeperiod_id);
CREATE INDEX timeperiod_inheritance_timeperiod_parent ON icinga_timeperiod_inheritance (parent_timeperiod_id);


CREATE TABLE icinga_timeperiod_range (
  timeperiod_id serial,
  range_key character varying(255) NOT NULL,
  range_value character varying(255) NOT NULL,
  range_type enum_timeperiod_range_type NOT NULL DEFAULT 'include',
  merge_behaviour enum_merge_behaviour NOT NULL DEFAULT 'set',
  PRIMARY KEY (timeperiod_id, range_type, range_key),
  CONSTRAINT icinga_timeperiod_range_timeperiod
  FOREIGN KEY (timeperiod_id)
    REFERENCES icinga_timeperiod (id)
    ON DELETE CASCADE
    ON UPDATE CASCADE
);

CREATE INDEX timeperiod_range_timeperiod ON icinga_timeperiod_range (timeperiod_id);
COMMENT ON COLUMN icinga_timeperiod_range.range_key IS 'monday, ...';
COMMENT ON COLUMN icinga_timeperiod_range.range_value IS '00:00-24:00, ...';
COMMENT ON COLUMN icinga_timeperiod_range.range_type IS 'include -> ranges {}, exclude ranges_ignore {} - not yet';
COMMENT ON COLUMN icinga_timeperiod_range.merge_behaviour IS 'set -> = {}, add -> += {}, substract -> -= {}';


CREATE TABLE director_job (
  id serial,
  job_name character varying(64) NOT NULL,
  job_class character varying(72) NOT NULL,
  disabled enum_boolean NOT NULL DEFAULT 'n',
  run_interval integer NOT NULL, -- seconds
  timeperiod_id integer DEFAULT NULL,
  last_attempt_succeeded enum_boolean DEFAULT NULL,
  ts_last_attempt timestamp with time zone DEFAULT NULL,
  ts_last_error timestamp with time zone DEFAULT NULL,
  last_error_message text NULL DEFAULT NULL,
  CONSTRAINT director_job_period
    FOREIGN KEY (timeperiod_id)
    REFERENCES icinga_timeperiod (id)
    ON DELETE RESTRICT
    ON UPDATE CASCADE,
  PRIMARY KEY (id)
);

CREATE UNIQUE INDEX director_job_unique_job_name ON director_job (job_name);


CREATE TABLE director_job_setting (
  job_id integer NOT NULL,
  setting_name character varying(64) NOT NULL,
  setting_value text DEFAULT NULL,
  PRIMARY KEY (job_id, setting_name),
  CONSTRAINT director_job_setting_job
    FOREIGN KEY (job_id)
    REFERENCES director_job (id)
    ON DELETE CASCADE
    ON UPDATE CASCADE
);

CREATE INDEX director_job_setting_job ON director_job_setting (job_id);


CREATE TABLE icinga_command (
  id serial,
  object_name character varying(255) NOT NULL,
  object_type enum_object_type_all NOT NULL,
  disabled enum_boolean NOT NULL DEFAULT 'n',
  methods_execute character varying(64) DEFAULT NULL,
  command text DEFAULT NULL,
-- env text DEFAULT NULL,
  timeout smallint DEFAULT NULL,
  zone_id integer DEFAULT NULL,
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


CREATE TABLE icinga_command_inheritance (
  command_id integer NOT NULL,
  parent_command_id integer NOT NULL,
  weight integer DEFAULT NULL,
  PRIMARY KEY (command_id, parent_command_id),
  CONSTRAINT icinga_command_inheritance_command
  FOREIGN KEY (command_id)
  REFERENCES icinga_command (id)
  ON DELETE CASCADE
  ON UPDATE CASCADE,
  CONSTRAINT icinga_command_inheritance_parent_command
  FOREIGN KEY (parent_command_id)
  REFERENCES icinga_command (id)
  ON DELETE RESTRICT
  ON UPDATE CASCADE
);

CREATE UNIQUE INDEX command_inheritance_unique_order ON icinga_command_inheritance (command_id, weight);
CREATE INDEX command_inheritance_command ON icinga_command_inheritance (command_id);
CREATE INDEX command_inheritance_command_parent ON icinga_command_inheritance (parent_command_id);


CREATE TABLE icinga_command_argument (
  id serial,
  command_id integer NOT NULL,
  argument_name character varying(64) NOT NULL,
  argument_value text DEFAULT NULL,
  argument_format enum_property_format DEFAULT NULL,
  key_string character varying(64) DEFAULT NULL,
  description text DEFAULT NULL,
  skip_key enum_boolean DEFAULT NULL,
  set_if character varying(255) DEFAULT NULL, -- (string expression, must resolve to a numeric value)
  set_if_format enum_property_format DEFAULT NULL,
  sort_order smallint DEFAULT NULL, -- -> order
  repeat_key enum_boolean DEFAULT NULL,
  required enum_boolean DEFAULT NULL,
  PRIMARY KEY (id),
  CONSTRAINT icinga_command_argument_command
  FOREIGN KEY (command_id)
    REFERENCES icinga_command (id)
    ON DELETE CASCADE
    ON UPDATE CASCADE
);

CREATE UNIQUE INDEX command_argument_unique_idx ON icinga_command_argument (command_id, argument_name);
CREATE INDEX command_argument_sort_idx ON icinga_command_argument (command_id, sort_order);
CREATE INDEX command_argument_command ON icinga_command_argument (command_id);
COMMENT ON COLUMN icinga_command_argument.argument_name IS '-x, --host';
COMMENT ON COLUMN icinga_command_argument.key_string IS 'Overrides name';
COMMENT ON COLUMN icinga_command_argument.repeat_key IS 'Useful with array values';


CREATE TABLE icinga_command_field (
  command_id integer NOT NULL,
  datafield_id integer NOT NULL,
  is_required enum_boolean NOT NULL,
  var_filter TEXT DEFAULT NULL,
  PRIMARY KEY (command_id, datafield_id),
  CONSTRAINT icinga_command_field_command
    FOREIGN KEY (command_id)
    REFERENCES icinga_command (id)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT icinga_command_field_datafield
    FOREIGN KEY (datafield_id)
    REFERENCES director_datafield (id)
    ON DELETE CASCADE
    ON UPDATE CASCADE
);


CREATE TABLE icinga_command_var (
  command_id integer NOT NULL,
  checksum bytea DEFAULT NULL UNIQUE CHECK(LENGTH(checksum) = 20),
  varname character varying(255) NOT NULL,
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
CREATE INDEX command_var_search_idx ON icinga_command_var (varname);
CREATE INDEX command_var_checksum ON icinga_command_var (checksum);


CREATE TABLE icinga_apiuser (
  id BIGSERIAL,
  object_name CHARACTER VARYING(255) NOT NULL,
  object_type enum_object_type_all NOT NULL,
  disabled enum_boolean NOT NULL DEFAULT 'n',
  password CHARACTER VARYING(255) DEFAULT NULL,
  client_dn CHARACTER VARYING(64) DEFAULT NULL,
  permissions TEXT DEFAULT NULL,
  PRIMARY KEY (id)
);

COMMENT ON COLUMN icinga_apiuser.permissions IS 'JSON-encoded permissions';


CREATE TABLE icinga_endpoint (
  id serial,
  zone_id integer DEFAULT NULL,
  object_name character varying(255) NOT NULL,
  object_type enum_object_type_all NOT NULL,
  disabled enum_boolean NOT NULL DEFAULT 'n',
  host character varying(255) DEFAULT NULL,
  port smallint DEFAULT NULL,
  log_duration character varying(32) DEFAULT NULL,
  apiuser_id INTEGER DEFAULT NULL,
  PRIMARY KEY (id),
  CONSTRAINT icinga_endpoint_zone
  FOREIGN KEY (zone_id)
    REFERENCES icinga_zone (id)
    ON DELETE RESTRICT
    ON UPDATE CASCADE,
  CONSTRAINT icinga_apiuser
  FOREIGN KEY (apiuser_id)
    REFERENCES icinga_apiuser (id)
    ON DELETE RESTRICT
    ON UPDATE CASCADE
);

CREATE UNIQUE INDEX endpoint_object_name ON icinga_endpoint (object_name);
CREATE INDEX endpoint_zone ON icinga_endpoint (zone_id);
COMMENT ON COLUMN icinga_endpoint.host IS 'IP address / hostname of remote node';
COMMENT ON COLUMN icinga_endpoint.port IS '5665 if not set';
COMMENT ON COLUMN icinga_endpoint.log_duration IS '1d if not set';


CREATE TABLE icinga_endpoint_inheritance (
  endpoint_id integer NOT NULL,
  parent_endpoint_id integer NOT NULL,
  weight integer DEFAULT NULL,
  PRIMARY KEY (endpoint_id, parent_endpoint_id),
  CONSTRAINT icinga_endpoint_inheritance_endpoint
  FOREIGN KEY (endpoint_id)
  REFERENCES icinga_endpoint (id)
  ON DELETE CASCADE
  ON UPDATE CASCADE,
  CONSTRAINT icinga_endpoint_inheritance_parent_endpoint
  FOREIGN KEY (parent_endpoint_id)
  REFERENCES icinga_endpoint (id)
  ON DELETE RESTRICT
  ON UPDATE CASCADE
);

CREATE UNIQUE INDEX endpoint_inheritance_unique_order ON icinga_endpoint_inheritance (endpoint_id, weight);
CREATE INDEX endpoint_inheritance_endpoint ON icinga_endpoint_inheritance (endpoint_id);
CREATE INDEX endpoint_inheritance_endpoint_parent ON icinga_endpoint_inheritance (parent_endpoint_id);


CREATE TABLE icinga_host_template_choice (
  id serial,
  object_name character varying(64) NOT NULL,
  description text DEFAULT NULL,
  min_required smallint NOT NULL DEFAULT 0,
  max_allowed smallint NOT NULL DEFAULT 1,
  required_template_id integer DEFAULT NULL,
  allowed_roles character varying(255) DEFAULT NULL,
  CONSTRAINT host_template_choice_required_template
  FOREIGN KEY (required_template_id)
  REFERENCES icinga_host (id)
    ON DELETE RESTRICT
    ON UPDATE CASCADE,
  PRIMARY KEY (id)
);

CREATE UNIQUE INDEX host_template_choice_object_name ON icinga_host_template_choice (object_name);
CREATE INDEX host_template_choice_required_template ON icinga_host_template_choice (required_template_id);

CREATE TABLE icinga_host (
  id serial,
  object_name character varying(255) NOT NULL,
  object_type enum_object_type_all NOT NULL,
  disabled enum_boolean NOT NULL DEFAULT 'n',
  display_name CHARACTER VARYING(255) DEFAULT NULL,
  address character varying(64) DEFAULT NULL,
  address6 character varying(45) DEFAULT NULL,
  check_command_id integer DEFAULT NULL,
  max_check_attempts integer DEFAULT NULL,
  check_period_id integer DEFAULT NULL,
  check_interval character varying(8) DEFAULT NULL,
  retry_interval character varying(8) DEFAULT NULL,
  check_timeout smallint DEFAULT NULL,
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
  has_agent enum_boolean DEFAULT NULL,
  master_should_connect enum_boolean DEFAULT NULL,
  accept_config enum_boolean DEFAULT NULL,
  api_key character varying(40) DEFAULT NULL,
  template_choice_id int DEFAULT NULL,
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
    ON UPDATE CASCADE,
  CONSTRAINT icinga_host_template_choice
    FOREIGN KEY (template_choice_id)
    REFERENCES icinga_host_template_choice (id)
    ON DELETE SET NULL
    ON UPDATE CASCADE
);


CREATE UNIQUE INDEX object_name_host ON icinga_host (object_name, zone_id);
CREATE UNIQUE INDEX host_api_key ON icinga_host (api_key);
CREATE INDEX host_zone ON icinga_host (zone_id);
CREATE INDEX host_timeperiod ON icinga_host (check_period_id);
CREATE INDEX host_check_command ON icinga_host (check_command_id);
CREATE INDEX host_event_command ON icinga_host (event_command_id);
CREATE INDEX host_command_endpoint ON icinga_host (command_endpoint_id);
CREATE INDEX host_template_choice ON icinga_host (template_choice_id);


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


CREATE TABLE icinga_host_field (
  host_id integer NOT NULL,
  datafield_id integer NOT NULL,
  is_required enum_boolean NOT NULL,
  var_filter TEXT DEFAULT NULL,
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


CREATE TABLE icinga_host_var (
  host_id integer NOT NULL,
  checksum bytea DEFAULT NULL UNIQUE CHECK(LENGTH(checksum) = 20),
  varname character varying(255) NOT NULL,
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
CREATE INDEX host_var_checksum ON icinga_host_var (checksum);


CREATE TABLE icinga_service_set (
  id serial,
  host_id integer DEFAULT NULL,
  object_name character varying(128) NOT NULL,
  object_type enum_object_type_all NOT NULL,
  description text DEFAULT NULL,
  assign_filter text DEFAULT NULL,
  PRIMARY KEY (id),
  CONSTRAINT icinga_service_set_host
  FOREIGN KEY (host_id)
  REFERENCES icinga_host (id)
  ON DELETE CASCADE
  ON UPDATE CASCADE
);

CREATE UNIQUE INDEX service_set_name ON icinga_service_set (object_name, host_id);
CREATE INDEX service_set_host ON icinga_service_set (host_id);


CREATE TABLE icinga_service_template_choice (
  id serial,
  object_name character varying(64) NOT NULL,
  description text DEFAULT NULL,
  min_required smallint NOT NULL DEFAULT 0,
  max_allowed smallint NOT NULL DEFAULT 1,
  required_template_id integer DEFAULT NULL,
  allowed_roles character varying(255) DEFAULT NULL,
  CONSTRAINT service_template_choice_required_template
  FOREIGN KEY (required_template_id)
  REFERENCES icinga_service (id)
    ON DELETE RESTRICT
    ON UPDATE CASCADE,
  PRIMARY KEY (id)
);

CREATE UNIQUE INDEX service_template_choice_object_name ON icinga_service_template_choice (object_name);
CREATE INDEX service_template_choice_required_template ON icinga_service_template_choice (required_template_id);


CREATE TABLE icinga_service (
  id serial,
  object_name character varying(255) NOT NULL,
  object_type enum_object_type_all NOT NULL,
  disabled enum_boolean DEFAULT 'n',
  display_name character varying(255) DEFAULT NULL,
  host_id INTEGER DEFAULT NULL,
  service_set_id integer DEFAULT NULL,
  check_command_id integer DEFAULT NULL,
  max_check_attempts integer DEFAULT NULL,
  check_period_id integer DEFAULT NULL,
  check_interval character varying(8) DEFAULT NULL,
  retry_interval character varying(8) DEFAULT NULL,
  check_timeout smallint DEFAULT NULL,
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
  use_agent enum_boolean DEFAULT NULL,
  apply_for character varying(255) DEFAULT NULL,
  use_var_overrides enum_boolean DEFAULT NULL,
  assign_filter text DEFAULT NULL,
  template_choice_id int DEFAULT NULL,
  PRIMARY KEY (id),
-- UNIQUE INDEX object_name (object_name, zone_id),
  CONSTRAINT icinga_service_host
    FOREIGN KEY (host_id)
    REFERENCES icinga_host (id)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
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
    ON UPDATE CASCADE,
  CONSTRAINT icinga_service_service_set
    FOREIGN KEY (service_set_id)
    REFERENCES icinga_service_set (id)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT icinga_service_template_choice
    FOREIGN KEY (template_choice_id)
    REFERENCES icinga_service_template_choice (id)
    ON DELETE SET NULL
    ON UPDATE CASCADE
);

CREATE INDEX service_zone ON icinga_service (zone_id);
CREATE INDEX service_timeperiod ON icinga_service (check_period_id);
CREATE INDEX service_check_command ON icinga_service (check_command_id);
CREATE INDEX service_event_command ON icinga_service (event_command_id);
CREATE INDEX service_command_endpoint ON icinga_service (command_endpoint_id);
CREATE INDEX service_template_choice ON icinga_service (template_choice_id);


CREATE TABLE icinga_service_inheritance (
  service_id integer NOT NULL,
  parent_service_id integer NOT NULL,
  weight integer DEFAULT NULL,
  PRIMARY KEY (service_id, parent_service_id),
  CONSTRAINT icinga_service_inheritance_service
  FOREIGN KEY (service_id)
  REFERENCES icinga_service (id)
  ON DELETE CASCADE
  ON UPDATE CASCADE,
  CONSTRAINT icinga_service_inheritance_parent_service
  FOREIGN KEY (parent_service_id)
  REFERENCES icinga_service (id)
  ON DELETE RESTRICT
  ON UPDATE CASCADE
);

CREATE UNIQUE INDEX service_inheritance_unique_order ON icinga_service_inheritance (service_id, weight);
CREATE INDEX service_inheritance_service ON icinga_service_inheritance (service_id);
CREATE INDEX service_inheritance_service_parent ON icinga_service_inheritance (parent_service_id);


CREATE TABLE icinga_service_var (
  service_id integer NOT NULL,
  checksum bytea DEFAULT NULL UNIQUE CHECK(LENGTH(checksum) = 20),
  varname character varying(255) NOT NULL,
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
CREATE INDEX service_var_checksum ON icinga_service_var (checksum);


CREATE TABLE icinga_service_field (
  service_id integer NOT NULL,
  datafield_id integer NOT NULL,
  is_required enum_boolean NOT NULL,
  var_filter TEXT DEFAULT NULL,
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


CREATE TABLE icinga_service_set_inheritance (
  service_set_id integer NOT NULL,
  parent_service_set_id integer NOT NULL,
  weight integer DEFAULT NULL,
  PRIMARY KEY (service_set_id, parent_service_set_id),
  CONSTRAINT icinga_service_set_inheritance_set
  FOREIGN KEY (service_set_id)
  REFERENCES icinga_service_set (id)
  ON DELETE CASCADE
  ON UPDATE CASCADE,
  CONSTRAINT icinga_service_set_inheritance_parent
  FOREIGN KEY (parent_service_set_id)
  REFERENCES icinga_service_set (id)
  ON DELETE RESTRICT
  ON UPDATE CASCADE
);

CREATE UNIQUE INDEX service_set_inheritance_unique_order ON icinga_service_set_inheritance (service_set_id, weight);
CREATE INDEX service_set_inheritance_set ON icinga_service_set_inheritance (service_set_id);
CREATE INDEX service_set_inheritance_parent ON icinga_service_set_inheritance (parent_service_set_id);


CREATE TABLE icinga_service_set_var (
  service_set_id integer NOT NULL,
  checksum bytea DEFAULT NULL UNIQUE CHECK(LENGTH(checksum) = 20),
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
CREATE INDEX service_set_var_checksum ON icinga_service_set_var (checksum);


CREATE TABLE icinga_hostgroup (
  id serial,
  object_name character varying(255) NOT NULL,
  object_type enum_object_type_all NOT NULL,
  disabled enum_boolean NOT NULL DEFAULT 'n',
  display_name character varying(255) DEFAULT NULL,
  assign_filter text DEFAULT NULL,
  PRIMARY KEY (id)
);

CREATE UNIQUE INDEX hostgroup_object_name ON icinga_hostgroup (object_name);
CREATE INDEX hostgroup_search_idx ON icinga_hostgroup (display_name);


-- -- TODO: probably useless
CREATE TABLE icinga_hostgroup_inheritance (
  hostgroup_id integer NOT NULL,
  parent_hostgroup_id integer NOT NULL,
  weight integer DEFAULT NULL,
  PRIMARY KEY (hostgroup_id, parent_hostgroup_id),
  CONSTRAINT icinga_hostgroup_inheritance_hostgroup
  FOREIGN KEY (hostgroup_id)
  REFERENCES icinga_hostgroup (id)
  ON DELETE CASCADE
  ON UPDATE CASCADE,
  CONSTRAINT icinga_hostgroup_inheritance_parent_hostgroup
  FOREIGN KEY (parent_hostgroup_id)
  REFERENCES icinga_hostgroup (id)
  ON DELETE RESTRICT
  ON UPDATE CASCADE
);

CREATE UNIQUE INDEX hostgroup_inheritance_unique_order ON icinga_hostgroup_inheritance (hostgroup_id, weight);
CREATE INDEX hostgroup_inheritance_hostgroup ON icinga_hostgroup_inheritance (hostgroup_id);
CREATE INDEX hostgroup_inheritance_hostgroup_parent ON icinga_hostgroup_inheritance (parent_hostgroup_id);


CREATE TABLE icinga_servicegroup (
  id serial,
  object_name character varying(255) DEFAULT NULL,
  object_type enum_object_type_all NOT NULL,
  disabled enum_boolean NOT NULL DEFAULT 'n',
  display_name character varying(255) DEFAULT NULL,
  assign_filter text DEFAULT NULL,
  PRIMARY KEY (id)
);

CREATE UNIQUE INDEX servicegroup_object_name ON icinga_servicegroup (object_name);
CREATE INDEX servicegroup_search_idx ON icinga_servicegroup (display_name);


CREATE TABLE icinga_servicegroup_inheritance (
  servicegroup_id integer NOT NULL,
  parent_servicegroup_id integer NOT NULL,
  weight integer DEFAULT NULL,
  PRIMARY KEY (servicegroup_id, parent_servicegroup_id),
  CONSTRAINT icinga_servicegroup_inheritance_servicegroup
  FOREIGN KEY (servicegroup_id)
  REFERENCES icinga_servicegroup (id)
  ON DELETE CASCADE
  ON UPDATE CASCADE,
  CONSTRAINT icinga_servicegroup_inheritance_parent_servicegroup
  FOREIGN KEY (parent_servicegroup_id)
  REFERENCES icinga_servicegroup (id)
  ON DELETE RESTRICT
  ON UPDATE CASCADE
);

CREATE UNIQUE INDEX servicegroup_inheritance_unique_order ON icinga_servicegroup_inheritance (servicegroup_id, weight);
CREATE INDEX servicegroup_inheritance_servicegroup ON icinga_servicegroup_inheritance (servicegroup_id);
CREATE INDEX servicegroup_inheritance_servicegroup_parent ON icinga_servicegroup_inheritance (parent_servicegroup_id);


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


CREATE TABLE icinga_hostgroup_host_resolved (
  hostgroup_id integer NOT NULL,
  host_id integer NOT NULL,
  PRIMARY KEY (hostgroup_id, host_id),
  CONSTRAINT icinga_hostgroup_host_resolved_host
  FOREIGN KEY (host_id)
    REFERENCES icinga_host (id)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT icinga_hostgroup_host_resolved_hostgroup
  FOREIGN KEY (hostgroup_id)
    REFERENCES icinga_hostgroup (id)
    ON DELETE CASCADE
    ON UPDATE CASCADE
);

CREATE INDEX hostgroup_host_resolved_host ON icinga_hostgroup_host_resolved (host_id);
CREATE INDEX hostgroup_host_resolved_hostgroup ON icinga_hostgroup_host_resolved (hostgroup_id);


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
  object_type enum_object_type_all NOT NULL,
  disabled enum_boolean NOT NULL DEFAULT 'n',
  display_name character varying(255) DEFAULT NULL,
  email character varying(255) DEFAULT NULL,
  pager character varying(255) DEFAULT NULL,
  enable_notifications enum_boolean DEFAULT NULL,
  period_id integer DEFAULT NULL,
  zone_id integer DEFAULT NULL,
  PRIMARY KEY (id),
  CONSTRAINT icinga_user_zone
  FOREIGN KEY (zone_id)
    REFERENCES icinga_zone (id)
    ON DELETE RESTRICT
    ON UPDATE CASCADE
);

CREATE UNIQUE INDEX user_object_name ON icinga_user (object_name, zone_id);
CREATE INDEX user_zone ON icinga_user (zone_id);


CREATE TABLE icinga_user_inheritance (
  user_id integer NOT NULL,
  parent_user_id integer NOT NULL,
  weight integer DEFAULT NULL,
  PRIMARY KEY (user_id, parent_user_id),
  CONSTRAINT icinga_user_inheritance_user
  FOREIGN KEY (user_id)
  REFERENCES icinga_user (id)
  ON DELETE CASCADE
  ON UPDATE CASCADE,
  CONSTRAINT icinga_user_inheritance_parent_user
  FOREIGN KEY (parent_user_id)
  REFERENCES icinga_user (id)
  ON DELETE RESTRICT
  ON UPDATE CASCADE
);

CREATE UNIQUE INDEX user_inheritance_unique_order ON icinga_user_inheritance (user_id, weight);
CREATE INDEX user_inheritance_user ON icinga_user_inheritance (user_id);
CREATE INDEX user_inheritance_user_parent ON icinga_user_inheritance (parent_user_id);


CREATE TABLE icinga_user_states_set (
  user_id integer NOT NULL,
  property enum_state_name NOT NULL,
  merge_behaviour enum_set_merge_behaviour NOT NULL DEFAULT 'override',
  PRIMARY KEY (user_id, property, merge_behaviour),
  CONSTRAINT icinga_user_filter_state_user
  FOREIGN KEY (user_id)
    REFERENCES icinga_user (id)
    ON DELETE CASCADE
    ON UPDATE CASCADE
);

CREATE INDEX user_states_set_user ON icinga_user_states_set (user_id);
COMMENT ON COLUMN icinga_user_states_set.merge_behaviour IS 'override: = [], extend: += [], blacklist: -= []';


CREATE TABLE icinga_user_types_set (
  user_id integer NOT NULL,
  property enum_type_name NOT NULL,
  merge_behaviour enum_set_merge_behaviour NOT NULL DEFAULT 'override',
  PRIMARY KEY (user_id, property, merge_behaviour),
  CONSTRAINT icinga_user_filter_type_user
  FOREIGN KEY (user_id)
    REFERENCES icinga_user (id)
    ON DELETE CASCADE
    ON UPDATE CASCADE
);

CREATE INDEX user_types_set_user ON icinga_user_types_set (user_id);
COMMENT ON COLUMN icinga_user_types_set.merge_behaviour IS 'override: = [], extend: += [], blacklist: -= []';


CREATE TABLE icinga_user_var (
  user_id integer NOT NULL,
  checksum bytea DEFAULT NULL UNIQUE CHECK(LENGTH(checksum) = 20),
  varname character varying(255) NOT NULL,
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
CREATE INDEX user_var_checksum ON icinga_user_var (checksum);


CREATE TABLE icinga_user_field (
  user_id integer NOT NULL,
  datafield_id integer NOT NULL,
  is_required enum_boolean NOT NULL,
  var_filter TEXT DEFAULT NULL,
  PRIMARY KEY (user_id, datafield_id),
  CONSTRAINT icinga_user_field_user
  FOREIGN KEY (user_id)
    REFERENCES icinga_user (id)
  ON DELETE CASCADE
  ON UPDATE CASCADE,
  CONSTRAINT icinga_user_field_datafield
  FOREIGN KEY (datafield_id)
    REFERENCES director_datafield (id)
  ON DELETE CASCADE
  ON UPDATE CASCADE
);

CREATE UNIQUE INDEX user_field_key ON icinga_user_field (user_id, datafield_id);
CREATE INDEX user_field_user ON icinga_user_field (user_id);
CREATE INDEX user_field_datafield ON icinga_user_field (datafield_id);
COMMENT ON COLUMN icinga_user_field.user_id IS 'Makes only sense for templates';


CREATE TABLE icinga_usergroup (
  id serial,
  object_name character varying(255) NOT NULL,
  object_type enum_object_type_all NOT NULL,
  disabled enum_boolean NOT NULL DEFAULT 'n',
  display_name character varying(255) DEFAULT NULL,
  PRIMARY KEY (id)
);

CREATE UNIQUE INDEX usergroup_search_idx ON icinga_usergroup (display_name);
CREATE INDEX usergroup_object_name ON icinga_usergroup (object_name);


CREATE TABLE icinga_usergroup_inheritance (
  usergroup_id integer NOT NULL,
  parent_usergroup_id integer NOT NULL,
  weight integer DEFAULT NULL,
  PRIMARY KEY (usergroup_id, parent_usergroup_id),
  CONSTRAINT icinga_usergroup_inheritance_usergroup
  FOREIGN KEY (usergroup_id)
  REFERENCES icinga_usergroup (id)
  ON DELETE CASCADE
  ON UPDATE CASCADE,
  CONSTRAINT icinga_usergroup_inheritance_parent_usergroup
  FOREIGN KEY (parent_usergroup_id)
  REFERENCES icinga_usergroup (id)
  ON DELETE RESTRICT
  ON UPDATE CASCADE
);

CREATE UNIQUE INDEX usergroup_inheritance_unique_order ON icinga_usergroup_inheritance (usergroup_id, weight);
CREATE INDEX usergroup_inheritance_usergroup ON icinga_usergroup_inheritance (usergroup_id);
CREATE INDEX usergroup_inheritance_usergroup_parent ON icinga_usergroup_inheritance (parent_usergroup_id);


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


CREATE TABLE icinga_notification (
  id serial,
  object_name CHARACTER VARYING(255) DEFAULT NULL,
  object_type enum_object_type_all NOT NULL,
  disabled enum_boolean NOT NULL DEFAULT 'n',
  apply_to enum_host_service NULL DEFAULT NULL,
  host_id integer DEFAULT NULL,
  service_id integer DEFAULT NULL,
  times_begin integer DEFAULT NULL,
  times_end integer DEFAULT NULL,
  notification_interval integer DEFAULT NULL,
  command_id integer DEFAULT NULL,
  period_id integer DEFAULT NULL,
  zone_id integer DEFAULT NULL,
  assign_filter text DEFAULT NULL,
  PRIMARY KEY (id),
  CONSTRAINT icinga_notification_host
    FOREIGN KEY (host_id)
    REFERENCES icinga_host (id)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT icinga_notification_service
    FOREIGN KEY (service_id)
    REFERENCES icinga_service (id)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT icinga_notification_command
    FOREIGN KEY (command_id)
    REFERENCES icinga_command (id)
    ON DELETE RESTRICT
    ON UPDATE CASCADE,
  CONSTRAINT icinga_notification_period
    FOREIGN KEY (period_id)
    REFERENCES icinga_timeperiod (id)
    ON DELETE RESTRICT
    ON UPDATE CASCADE,
  CONSTRAINT icinga_notification_zone
    FOREIGN KEY (zone_id)
    REFERENCES icinga_zone (id)
    ON DELETE RESTRICT
    ON UPDATE CASCADE
);


CREATE TABLE icinga_notification_user (
  notification_id integer NOT NULL,
  user_id integer NOT NULL,
  PRIMARY KEY (notification_id, user_id),
  CONSTRAINT icinga_notification_user_user
  FOREIGN KEY (user_id)
    REFERENCES icinga_user (id)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT icinga_notification_user_notification
  FOREIGN KEY (notification_id)
    REFERENCES icinga_notification (id)
    ON DELETE CASCADE
    ON UPDATE CASCADE
);

CREATE TABLE icinga_notification_usergroup (
  notification_id integer NOT NULL,
  usergroup_id integer NOT NULL,
  PRIMARY KEY (notification_id, usergroup_id),
  CONSTRAINT icinga_notification_usergroup_usergroup
  FOREIGN KEY (usergroup_id)
    REFERENCES icinga_usergroup (id)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT icinga_notification_usergroup_notification
  FOREIGN KEY (notification_id)
    REFERENCES icinga_notification (id)
    ON DELETE CASCADE
    ON UPDATE CASCADE
);


CREATE TABLE import_source (
  id serial,
  source_name character varying(64) NOT NULL,
  key_column character varying(64) NOT NULL,
  provider_class character varying(72) NOT NULL,
  import_state enum_sync_state NOT NULL DEFAULT 'unknown',
  last_error_message text NULL DEFAULT NULL,
  last_attempt timestamp with time zone NULL DEFAULT NULL,
  description text DEFAULT NULL,
  PRIMARY KEY (id)
);

CREATE INDEX import_source_search_idx ON import_source (key_column);


CREATE TABLE import_source_setting (
  source_id integer NOT NULL,
  setting_name character varying(64) NOT NULL,
  setting_value text NOT NULL,
  PRIMARY KEY (source_id, setting_name),
  CONSTRAINT import_source_settings_source
  FOREIGN KEY (source_id)
  REFERENCES import_source (id)
  ON DELETE CASCADE
  ON UPDATE CASCADE
);

CREATE INDEX import_source_setting_source ON import_source_setting (source_id);


CREATE TABLE import_row_modifier (
  id bigserial,
  source_id integer NOT NULL,
  property_name character varying(255) NOT NULL,
  target_property character varying(255) DEFAULT NULL,
  provider_class character varying(72) NOT NULL,
  priority integer NOT NULL,
  description text DEFAULT NULL,
  PRIMARY KEY (id),
  CONSTRAINT row_modifier_import_source
    FOREIGN KEY (source_id)
    REFERENCES import_source (id)
    ON DELETE CASCADE
    ON UPDATE CASCADE
);

CREATE INDEX import_row_modifier_search_idx ON import_row_modifier (property_name);
CREATE UNIQUE INDEX import_row_modifier_prio
  ON import_row_modifier (source_id, priority);


CREATE TABLE import_row_modifier_setting (
  row_modifier_id serial,
  setting_name character varying(64) NOT NULL,
  setting_value TEXT DEFAULT NULL,
  PRIMARY KEY (row_modifier_id, setting_name),
  CONSTRAINT row_modifier_settings
    FOREIGN KEY (row_modifier_id)
    REFERENCES import_row_modifier (id)
    ON DELETE CASCADE
    ON UPDATE CASCADE
);


CREATE TABLE imported_rowset (
  checksum bytea CHECK(LENGTH(checksum) = 20),
  PRIMARY KEY (checksum)
);


CREATE TABLE import_run (
  id serial,
  source_id integer NOT NULL,
  rowset_checksum bytea CHECK(LENGTH(rowset_checksum) = 20),
  start_time timestamp with time zone NOT NULL,
  end_time timestamp with time zone DEFAULT NULL,
  succeeded enum_boolean DEFAULT NULL,
  PRIMARY KEY (id),
  CONSTRAINT import_run_source
  FOREIGN KEY (source_id)
  REFERENCES import_source (id)
  ON DELETE CASCADE
  ON UPDATE RESTRICT,
  CONSTRAINT import_run_rowset
  FOREIGN KEY (rowset_checksum)
  REFERENCES imported_rowset (checksum)
  ON DELETE RESTRICT
  ON UPDATE CASCADE
);

CREATE INDEX import_run_import_source ON import_run (source_id);
CREATE INDEX import_run_rowset ON import_run (rowset_checksum);


CREATE TABLE imported_row (
  checksum bytea CHECK(LENGTH(checksum) = 20),
  object_name character varying(255) NOT NULL,
  PRIMARY KEY (checksum)
);

COMMENT ON COLUMN imported_row.checksum IS 'sha1(object_name;property_checksum;...)';


CREATE TABLE imported_rowset_row (
  rowset_checksum bytea CHECK(LENGTH(rowset_checksum) = 20),
  row_checksum bytea CHECK(LENGTH(row_checksum) = 20),
  PRIMARY KEY (rowset_checksum, row_checksum),
  CONSTRAINT imported_rowset_row_rowset
    FOREIGN KEY (rowset_checksum)
    REFERENCES imported_rowset (checksum)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT imported_rowset_row_row
    FOREIGN KEY (row_checksum)
    REFERENCES imported_row (checksum)
    ON DELETE RESTRICT
    ON UPDATE CASCADE
);

CREATE INDEX imported_rowset_row_rowset_checksum ON imported_rowset_row (rowset_checksum);
CREATE INDEX imported_rowset_row_row_checksum ON imported_rowset_row (row_checksum);


CREATE TABLE imported_property (
  checksum bytea CHECK(LENGTH(checksum) = 20),
  property_name character varying(64) NOT NULL,
  property_value text NOT NULL,
  format enum_property_format,
  PRIMARY KEY (checksum)
);

CREATE INDEX imported_property_search_idx ON imported_property (property_name);


CREATE TABLE imported_row_property (
  row_checksum bytea CHECK(LENGTH(row_checksum) = 20),
  property_checksum bytea CHECK(LENGTH(property_checksum) = 20),
  PRIMARY KEY (row_checksum, property_checksum),
  CONSTRAINT imported_row_property_row
  FOREIGN KEY (row_checksum)
  REFERENCES imported_row (checksum)
  ON DELETE CASCADE
  ON UPDATE CASCADE,
  CONSTRAINT imported_row_property_property
  FOREIGN KEY (property_checksum)
  REFERENCES imported_property (checksum)
  ON DELETE RESTRICT
  ON UPDATE CASCADE
);

CREATE INDEX imported_row_property_row_checksum ON imported_row_property (row_checksum);
CREATE INDEX imported_row_property_property_checksum ON imported_row_property (property_checksum);


CREATE TABLE sync_rule (
  id serial,
  rule_name character varying(255) NOT NULL,
  object_type enum_sync_rule_object_type NOT NULL,
  update_policy enum_sync_rule_update_policy NOT NULL,
  purge_existing enum_boolean NOT NULL DEFAULT 'n',
  filter_expression text DEFAULT NULL,
  sync_state enum_sync_state NOT NULL DEFAULT 'unknown',
  last_error_message text NULL DEFAULT NULL,
  last_attempt timestamp with time zone NULL DEFAULT NULL,
  description text DEFAULT NULL,
  PRIMARY KEY (id)
);


CREATE TABLE sync_property (
  id serial,
  rule_id integer NOT NULL,
  source_id integer NOT NULL,
  source_expression character varying(255) NOT NULL,
  destination_field character varying(64),
  priority smallint NOT NULL,
  filter_expression text DEFAULT NULL,
  merge_policy enum_sync_property_merge_policy DEFAULT NULL,
  PRIMARY KEY (id),
  CONSTRAINT sync_property_rule
  FOREIGN KEY (rule_id)
  REFERENCES sync_rule (id)
  ON DELETE CASCADE
  ON UPDATE CASCADE,
  CONSTRAINT sync_property_source
  FOREIGN KEY (source_id)
  REFERENCES import_source (id)
  ON DELETE RESTRICT
  ON UPDATE CASCADE
);

CREATE INDEX sync_property_rule ON sync_property (rule_id);
CREATE INDEX sync_property_source ON sync_property (source_id);


CREATE TABLE sync_run (
  id bigserial,
  rule_id integer DEFAULT NULL,
  rule_name character varying(255) NOT NULL,
  start_time TIMESTAMP WITH TIME ZONE NOT NULL,
  duration_ms integer DEFAULT NULL,
  objects_deleted integer DEFAULT 0,
  objects_created integer DEFAULT 0,
  objects_modified integer DEFAULT 0,
  last_former_activity bytea DEFAULT NULL CHECK(LENGTH(last_former_activity) = 20),
  last_related_activity bytea DEFAULT NULL CHECK(LENGTH(last_related_activity) = 20),
  PRIMARY KEY (id),
  CONSTRAINT sync_run_rule
    FOREIGN KEY (rule_id)
    REFERENCES sync_rule (id)
    ON DELETE SET NULL
    ON UPDATE CASCADE
);


CREATE TABLE icinga_notification_states_set (
  notification_id integer NOT NULL,
  property enum_state_name NOT NULL,
  merge_behaviour enum_set_merge_behaviour NOT NULL DEFAULT 'override',
  PRIMARY KEY (notification_id, property, merge_behaviour),
  CONSTRAINT icinga_notification_states_set_notification
  FOREIGN KEY (notification_id)
  REFERENCES icinga_notification (id)
  ON DELETE CASCADE
  ON UPDATE CASCADE
);

COMMENT ON COLUMN icinga_notification_states_set.merge_behaviour IS 'override: = [], extend: += [], blacklist: -= []';


CREATE TABLE icinga_notification_types_set (
  notification_id integer NOT NULL,
  property enum_type_name NOT NULL,
  merge_behaviour enum_set_merge_behaviour NOT NULL DEFAULT 'override',
  PRIMARY KEY (notification_id, property, merge_behaviour),
  CONSTRAINT icinga_notification_types_set_notification
  FOREIGN KEY (notification_id)
  REFERENCES icinga_notification (id)
  ON DELETE CASCADE
  ON UPDATE CASCADE
);

COMMENT ON COLUMN icinga_notification_types_set.merge_behaviour IS 'override: = [], extend: += [], blacklist: -= []';


CREATE TABLE icinga_notification_var (
  notification_id integer NOT NULL,
  checksum bytea DEFAULT NULL UNIQUE CHECK(LENGTH(checksum) = 20),
  varname VARCHAR(255) NOT NULL,
  varvalue TEXT DEFAULT NULL,
  format enum_property_format,
  PRIMARY KEY (notification_id, varname),
  CONSTRAINT icinga_notification_var_notification
  FOREIGN KEY (notification_id)
  REFERENCES icinga_notification (id)
  ON DELETE CASCADE
  ON UPDATE CASCADE
);

CREATE INDEX notification_var_command ON icinga_notification_var (notification_id);
CREATE UNIQUE INDEX notification_var_search_idx ON icinga_notification_var (varname);
CREATE INDEX notification_var_checksum ON icinga_notification_var (checksum);


CREATE TABLE icinga_notification_field (
  notification_id integer NOT NULL,
  datafield_id integer NOT NULL,
  is_required enum_boolean NOT NULL,
  var_filter TEXT DEFAULT NULL,
  PRIMARY KEY (notification_id, datafield_id),
  CONSTRAINT icinga_notification_field_notification
  FOREIGN KEY (notification_id)
    REFERENCES icinga_notification (id)
  ON DELETE CASCADE
  ON UPDATE CASCADE,
  CONSTRAINT icinga_notification_field_datafield
  FOREIGN KEY (datafield_id)
    REFERENCES director_datafield (id)
  ON DELETE CASCADE
  ON UPDATE CASCADE
);

CREATE UNIQUE INDEX notification_field_key ON icinga_notification_field (notification_id, datafield_id);
CREATE INDEX notification_field_notification ON icinga_notification_field (notification_id);
CREATE INDEX notification_field_datafield ON icinga_notification_field (datafield_id);
COMMENT ON COLUMN icinga_notification_field.notification_id IS 'Makes only sense for templates';


CREATE TABLE icinga_notification_inheritance (
  notification_id integer NOT NULL,
  parent_notification_id integer NOT NULL,
  weight integer DEFAULT NULL,
  PRIMARY KEY (notification_id, parent_notification_id),
  CONSTRAINT icinga_notification_inheritance_notification
  FOREIGN KEY (notification_id)
  REFERENCES icinga_notification (id)
  ON DELETE CASCADE
  ON UPDATE CASCADE,
  CONSTRAINT icinga_notification_inheritance_parent_notification
  FOREIGN KEY (parent_notification_id)
  REFERENCES icinga_notification (id)
  ON DELETE RESTRICT
  ON UPDATE CASCADE
);

CREATE UNIQUE INDEX notification_inheritance ON icinga_notification_inheritance (notification_id, weight);


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


CREATE TABLE icinga_dependency (
  id serial,
  object_name character varying(255) NOT NULL,
  object_type enum_object_type_all NOT NULL,
  disabled enum_boolean DEFAULT 'n',
  apply_to enum_host_service NULL DEFAULT NULL,
  parent_host_id integer DEFAULT NULL,
  parent_service_id integer DEFAULT NULL,
  child_host_id integer DEFAULT NULL,
  child_service_id integer DEFAULT NULL,
  disable_checks enum_boolean DEFAULT NULL,
  disable_notifications enum_boolean DEFAULT NULL,
  ignore_soft_states enum_boolean DEFAULT NULL,
  period_id integer DEFAULT NULL,
  zone_id integer DEFAULT NULL,
  assign_filter text DEFAULT NULL,
  parent_service_by_name character varying(255),
  PRIMARY KEY (id),
  CONSTRAINT icinga_dependency_parent_host
    FOREIGN KEY (parent_host_id)
    REFERENCES icinga_host (id)
    ON DELETE RESTRICT
    ON UPDATE CASCADE,
  CONSTRAINT icinga_dependency_parent_service
    FOREIGN KEY (parent_service_id)
    REFERENCES icinga_service (id)
    ON DELETE RESTRICT
    ON UPDATE CASCADE,
  CONSTRAINT icinga_dependency_child_host
    FOREIGN KEY (child_host_id)
    REFERENCES icinga_host (id)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT icinga_dependency_child_service
    FOREIGN KEY (child_service_id)
    REFERENCES icinga_service (id)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT icinga_dependency_period
    FOREIGN KEY period (period_id)
    REFERENCES icinga_timeperiod (id)
    ON DELETE RESTRICT
    ON UPDATE CASCADE,
  CONSTRAINT icinga_dependency_zone
    FOREIGN KEY zone (zone_id)
    REFERENCES icinga_zone (id)
    ON DELETE RESTRICT
    ON UPDATE CASCADE
);

CREATE INDEX dependency_parent_host ON icinga_dependency (parent_host_id);
CREATE INDEX dependency_parent_service ON icinga_dependency (parent_service_id);
CREATE INDEX dependency_child_host ON icinga_dependency (child_host_id);
CREATE INDEX dependency_child_service ON icinga_dependency (child_service_id);
CREATE INDEX dependency_period ON icinga_dependency (period_id);
CREATE INDEX dependency_zone ON icinga_dependency (zone_id);


CREATE TABLE icinga_dependency_inheritance (
  dependency_id integer NOT NULL,
  parent_dependency_id integer NOT NULL,
  weight integer DEFAULT NULL,
  PRIMARY KEY (dependency_id, parent_dependency_id),
  CONSTRAINT icinga_dependency_inheritance_dependency
    FOREIGN KEY (dependency_id)
    REFERENCES icinga_dependency (id)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT icinga_dependency_inheritance_parent_dependency
    FOREIGN KEY (parent_dependency_id)
    REFERENCES icinga_dependency (id)
    ON DELETE RESTRICT
    ON UPDATE CASCADE
);

CREATE UNIQUE INDEX dependency_inheritance_unique_order ON icinga_dependency_inheritance (dependency_id, weight);
CREATE INDEX dependency_inheritance_dependency ON icinga_dependency_inheritance (dependency_id);
CREATE INDEX dependency_inheritance_dependency_parent ON icinga_dependency_inheritance (parent_dependency_id);


CREATE TABLE icinga_dependency_states_set (
  icinga_dependency integer NOT NULL,
  property enum_state_name NOT NULL,
  merge_behaviour enum_set_merge_behaviour NOT NULL DEFAULT 'override',
  PRIMARY KEY (dependency_id, property, merge_behaviour),
  CONSTRAINT icinga_dependency_states_set_dependency
    FOREIGN KEY (dependency_id)
    REFERENCES icinga_dependency (id)
    ON DELETE CASCADE
    ON UPDATE CASCADE
);

CREATE INDEX dependency_states_set_dependency ON icinga_dependency_states_set (user_id);
COMMENT ON COLUMN icinga_dependency_states_set.merge_behaviour IS 'override: = [], extend: += [], blacklist: -= []';


INSERT INTO director_schema_migration
  (schema_version, migration_time)
  VALUES (144, NOW());
