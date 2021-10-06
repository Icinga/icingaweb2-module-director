CREATE TABLE director_branch (
  uuid  bytea NOT NULL UNIQUE CHECK(LENGTH(uuid) = 16),
  owner character varying(255) NOT NULL,
  branch_name character varying(255) NOT NULL,
  description text DEFAULT NULL,
  ts_merge_request bigint DEFAULT NULL,
  PRIMARY KEY(uuid)
);
CREATE UNIQUE INDEX branch_branch_name ON director_branch (branch_name);

CREATE TYPE enum_branch_action AS ENUM('create', 'modify', 'delete');

CREATE TABLE director_branch_activity (
  timestamp_ns bigint NOT NULL,
  object_uuid bytea NOT NULL CHECK(LENGTH(object_uuid) = 16),
  branch_uuid bytea NOT NULL CHECK(LENGTH(branch_uuid) = 16),
  action enum_branch_action NOT NULL,
  object_table character varying(64) NOT NULL,
  author character varying(255) NOT NULL,
  former_properties text NOT NULL,
  modified_properties text NOT NULL,
  PRIMARY KEY (timestamp_ns),
  CONSTRAINT branch_activity_branch
  FOREIGN KEY (branch_uuid)
    REFERENCES director_branch (uuid)
      ON DELETE CASCADE
      ON UPDATE CASCADE
);
CREATE INDEX branch_activity_object_uuid ON director_branch_activity (object_uuid);
CREATE INDEX branch_activity_branch_uuid ON director_branch_activity (branch_uuid);


CREATE TABLE branched_icinga_host (
  uuid bytea NOT NULL UNIQUE CHECK(LENGTH(uuid) = 16),
  branch_uuid bytea NOT NULL CHECK(LENGTH(branch_uuid) = 16),
  branch_created enum_boolean NOT NULL DEFAULT 'n',
  branch_deleted enum_boolean NOT NULL DEFAULT 'n',

  object_name character varying(255) DEFAULT NULL,
  object_type enum_object_type_all DEFAULT NULL,
  disabled enum_boolean DEFAULT NULL,
  display_name CHARACTER VARYING(255) DEFAULT NULL,
  address character varying(255) DEFAULT NULL,
  address6 character varying(45) DEFAULT NULL,
  check_command character varying(255) DEFAULT NULL,
  max_check_attempts integer DEFAULT NULL,
  check_period character varying(255) DEFAULT NULL,
  check_interval character varying(8) DEFAULT NULL,
  retry_interval character varying(8) DEFAULT NULL,
  check_timeout smallint DEFAULT NULL,
  enable_notifications enum_boolean DEFAULT NULL,
  enable_active_checks enum_boolean DEFAULT NULL,
  enable_passive_checks enum_boolean DEFAULT NULL,
  enable_event_handler enum_boolean DEFAULT NULL,
  enable_flapping enum_boolean DEFAULT NULL,
  enable_perfdata enum_boolean DEFAULT NULL,
  event_command character varying(255) DEFAULT NULL,
  flapping_threshold_high smallint default null,
  flapping_threshold_low smallint default null,
  volatile enum_boolean DEFAULT NULL,
  zone character varying(255) DEFAULT NULL,
  command_endpoint character varying(255) DEFAULT NULL,
  notes text DEFAULT NULL,
  notes_url character varying(255) DEFAULT NULL,
  action_url character varying(255) DEFAULT NULL,
  icon_image character varying(255) DEFAULT NULL,
  icon_image_alt character varying(255) DEFAULT NULL,
  has_agent enum_boolean DEFAULT NULL,
  master_should_connect enum_boolean DEFAULT NULL,
  accept_config enum_boolean DEFAULT NULL,
  api_key character varying(40) DEFAULT NULL,
  -- template_choice character varying(255) DEFAULT NULL, -- TODO: Forbid them!

  imports TEXT DEFAULT NULL,
  groups TEXT DEFAULT NULL,
  vars TEXT DEFAULT NULL,

  set_null TEXT DEFAULT NULL,
  PRIMARY KEY (branch_uuid, uuid),
  CONSTRAINT icinga_host_branch
    FOREIGN KEY (branch_uuid)
    REFERENCES director_branch (uuid)
    ON DELETE CASCADE
    ON UPDATE CASCADE
);

CREATE UNIQUE INDEX host_branch_object_name ON branched_icinga_host (branch_uuid, object_name);
CREATE INDEX branched_host_search_object_name ON branched_icinga_host (object_name);
CREATE INDEX branched_host_search_display_name ON branched_icinga_host (display_name);


CREATE TABLE branched_icinga_hostgroup (
  uuid bytea NOT NULL UNIQUE CHECK(LENGTH(uuid) = 16),
  branch_uuid bytea NOT NULL CHECK(LENGTH(branch_uuid) = 16),
  branch_created enum_boolean NOT NULL DEFAULT 'n',
  branch_deleted enum_boolean NOT NULL DEFAULT 'n',

  object_name character varying(255) DEFAULT NULL,
  object_type enum_object_type_all DEFAULT NULL,
  disabled enum_boolean DEFAULT NULL,
  display_name character varying(255) DEFAULT NULL,
  assign_filter text DEFAULT NULL,
  set_null TEXT DEFAULT NULL,
  PRIMARY KEY (branch_uuid, uuid),
  CONSTRAINT icinga_hostgroup_branch
    FOREIGN KEY (branch_uuid)
      REFERENCES director_branch (uuid)
      ON DELETE CASCADE
      ON UPDATE CASCADE
);

CREATE UNIQUE INDEX hostgroup_branch_object_name ON branched_icinga_hostgroup (branch_uuid, object_name);
CREATE INDEX branched_hostgroup_search_object_name ON branched_icinga_hostgroup (object_name);
CREATE INDEX branched_hostgroup_search_display_name ON branched_icinga_hostgroup (display_name);


CREATE TABLE branched_icinga_servicegroup (
  uuid bytea NOT NULL UNIQUE CHECK(LENGTH(uuid) = 16),
  branch_uuid bytea NOT NULL CHECK(LENGTH(branch_uuid) = 16),
  branch_created enum_boolean NOT NULL DEFAULT 'n',
  branch_deleted enum_boolean NOT NULL DEFAULT 'n',

  object_name character varying(255) DEFAULT NULL,
  object_type enum_object_type_all DEFAULT NULL,
  disabled enum_boolean DEFAULT NULL,
  display_name character varying(255) DEFAULT NULL,
  assign_filter text DEFAULT NULL,
  set_null TEXT DEFAULT NULL,
  PRIMARY KEY (branch_uuid, uuid),
  CONSTRAINT icinga_servicegroup_branch
    FOREIGN KEY (branch_uuid)
      REFERENCES director_branch (uuid)
      ON DELETE CASCADE
      ON UPDATE CASCADE
);

CREATE UNIQUE INDEX servicegroup_branch_object_name ON branched_icinga_servicegroup (branch_uuid, object_name);
CREATE INDEX branched_servicegroup_search_object_name ON branched_icinga_servicegroup (object_name);
CREATE INDEX branched_servicegroup_search_display_name ON branched_icinga_servicegroup (display_name);


CREATE TABLE branched_icinga_usergroup (
  uuid bytea NOT NULL UNIQUE CHECK(LENGTH(uuid) = 16),
  branch_uuid bytea NOT NULL CHECK(LENGTH(branch_uuid) = 16),
  branch_created enum_boolean NOT NULL DEFAULT 'n',
  branch_deleted enum_boolean NOT NULL DEFAULT 'n',

  object_name character varying(255) DEFAULT NULL,
  object_type enum_object_type_all DEFAULT NULL,
  disabled enum_boolean DEFAULT NULL,
  display_name character varying(255) DEFAULT NULL,
  assign_filter text DEFAULT NULL,
  set_null TEXT DEFAULT NULL,
  PRIMARY KEY (branch_uuid, uuid),
  CONSTRAINT icinga_usergroup_branch
    FOREIGN KEY (branch_uuid)
      REFERENCES director_branch (uuid)
      ON DELETE CASCADE
      ON UPDATE CASCADE
);

CREATE UNIQUE INDEX usergroup_branch_object_name ON branched_icinga_usergroup (branch_uuid, object_name);
CREATE INDEX branched_usergroup_search_object_name ON branched_icinga_usergroup (object_name);
CREATE INDEX branched_usergroup_search_display_name ON branched_icinga_usergroup (display_name);


CREATE TABLE branched_icinga_user (
  uuid bytea NOT NULL UNIQUE CHECK(LENGTH(uuid) = 16),
  branch_uuid bytea NOT NULL CHECK(LENGTH(branch_uuid) = 16),
  branch_created enum_boolean NOT NULL DEFAULT 'n',
  branch_deleted enum_boolean NOT NULL DEFAULT 'n',

  object_name character varying(255) DEFAULT NULL,
  object_type enum_object_type_all DEFAULT NULL,
  disabled enum_boolean DEFAULT NULL,
  display_name character varying(255) DEFAULT NULL,
  email character varying(255) DEFAULT NULL,
  pager character varying(255) DEFAULT NULL,
  enable_notifications enum_boolean DEFAULT NULL,
  period character varying(255) DEFAULT NULL,
  zone character varying(255) DEFAULT NULL,

  imports TEXT DEFAULT NULL,
  groups TEXT DEFAULT NULL,
  vars TEXT DEFAULT NULL,
  set_null TEXT DEFAULT NULL,
  PRIMARY KEY (branch_uuid, uuid),
  CONSTRAINT icinga_user_branch
    FOREIGN KEY (branch_uuid)
    REFERENCES director_branch (uuid)
    ON DELETE CASCADE
    ON UPDATE CASCADE
);

CREATE UNIQUE INDEX user_branch_object_name ON branched_icinga_user (branch_uuid, object_name);
CREATE INDEX branched_user_search_object_name ON branched_icinga_user (object_name);
CREATE INDEX branched_user_search_display_name ON branched_icinga_user (display_name);


CREATE TABLE branched_icinga_zone (
  uuid bytea NOT NULL UNIQUE CHECK(LENGTH(uuid) = 16),
  branch_uuid bytea NOT NULL CHECK(LENGTH(branch_uuid) = 16),
  branch_created enum_boolean NOT NULL DEFAULT 'n',
  branch_deleted enum_boolean NOT NULL DEFAULT 'n',

  object_name character varying(255) DEFAULT NULL,
  parent character varying(255) DEFAULT NULL,
  object_type enum_object_type_all DEFAULT NULL,
  disabled enum_boolean DEFAULT NULL,
  is_global enum_boolean DEFAULT NULL,

  imports TEXT DEFAULT NULL,
  set_null TEXT DEFAULT NULL,
  PRIMARY KEY (branch_uuid, uuid),
  CONSTRAINT icinga_zone_branch
    FOREIGN KEY (branch_uuid)
    REFERENCES director_branch (uuid)
    ON DELETE CASCADE
    ON UPDATE CASCADE
);

CREATE UNIQUE INDEX zone_branch_object_name ON branched_icinga_zone (branch_uuid, object_name);
CREATE INDEX branched_zone_search_object_name ON branched_icinga_zone (object_name);


CREATE TABLE branched_icinga_timeperiod (
  uuid bytea NOT NULL UNIQUE CHECK(LENGTH(uuid) = 16),
  branch_uuid bytea NOT NULL CHECK(LENGTH(branch_uuid) = 16),
  branch_created enum_boolean NOT NULL DEFAULT 'n',
  branch_deleted enum_boolean NOT NULL DEFAULT 'n',

  object_name character varying(255) DEFAULT NULL,
  display_name character varying(255) DEFAULT NULL,
  update_method character varying(64) DEFAULT NULL,
  zone character varying(255) DEFAULT NULL,
  object_type enum_object_type_all DEFAULT NULL,
  disabled enum_boolean DEFAULT NULL,
  prefer_includes enum_boolean DEFAULT NULL,

  imports TEXT DEFAULT NULL,
  ranges TEXT DEFAULT NULL,
  set_null TEXT DEFAULT NULL,
  PRIMARY KEY (branch_uuid, uuid),
  CONSTRAINT icinga_timeperiod_branch
    FOREIGN KEY (branch_uuid)
      REFERENCES director_branch (uuid)
      ON DELETE CASCADE
      ON UPDATE CASCADE
);

CREATE UNIQUE INDEX timeperiod_branch_object_name ON branched_icinga_timeperiod (branch_uuid, object_name);
CREATE INDEX branched_timeperiod_search_object_name ON branched_icinga_timeperiod (object_name);
CREATE INDEX branched_timeperiod_search_display_name ON branched_icinga_timeperiod (display_name);


CREATE TABLE branched_icinga_command (
  uuid bytea NOT NULL UNIQUE CHECK(LENGTH(uuid) = 16),
  branch_uuid bytea NOT NULL CHECK(LENGTH(branch_uuid) = 16),
  branch_created enum_boolean NOT NULL DEFAULT 'n',
  branch_deleted enum_boolean NOT NULL DEFAULT 'n',

  object_name character varying(255) DEFAULT NULL,
  object_type enum_object_type_all DEFAULT NULL,
  disabled enum_boolean NOT NULL DEFAULT NULL,
  methods_execute character varying(64) DEFAULT NULL,
  command text DEFAULT NULL,
  is_string enum_boolean DEFAULT NULL,
-- env text DEFAULT NULL,
  timeout smallint DEFAULT NULL,
  zone character varying(255) DEFAULT NULL,

  imports TEXT DEFAULT NULL,
  arguments TEXT DEFAULT NULL,
  set_null TEXT DEFAULT NULL,
  PRIMARY KEY (branch_uuid, uuid),
  CONSTRAINT icinga_command_branch
      FOREIGN KEY (branch_uuid)
          REFERENCES director_branch (uuid)
          ON DELETE CASCADE
          ON UPDATE CASCADE
);

CREATE UNIQUE INDEX command_branch_object_name ON branched_icinga_command (branch_uuid, object_name);
CREATE INDEX branched_command_search_object_name ON branched_icinga_command (object_name);


CREATE TABLE branched_icinga_apiuser (
  uuid bytea NOT NULL UNIQUE CHECK(LENGTH(uuid) = 16),
  branch_uuid bytea NOT NULL CHECK(LENGTH(branch_uuid) = 16),
  branch_created enum_boolean NOT NULL DEFAULT 'n',
  branch_deleted enum_boolean NOT NULL DEFAULT 'n',

  object_name CHARACTER VARYING(255) DEFAULT NULL,
  object_type enum_object_type_all DEFAULT NULL,
  disabled enum_boolean NOT NULL DEFAULT NULL,
  password CHARACTER VARYING(255) DEFAULT NULL,
  client_dn CHARACTER VARYING(64) DEFAULT NULL,
  permissions TEXT DEFAULT NULL,

  set_null TEXT DEFAULT NULL,
  PRIMARY KEY (branch_uuid, uuid),
  CONSTRAINT icinga_apiuser_branch
      FOREIGN KEY (branch_uuid)
          REFERENCES director_branch (uuid)
          ON DELETE CASCADE
          ON UPDATE CASCADE
);

CREATE UNIQUE INDEX apiuser_branch_object_name ON branched_icinga_apiuser (branch_uuid, object_name);
CREATE INDEX branched_apiuser_search_object_name ON branched_icinga_apiuser (object_name);


CREATE TABLE branched_icinga_endpoint (
  uuid bytea NOT NULL UNIQUE CHECK(LENGTH(uuid) = 16),
  branch_uuid bytea NOT NULL CHECK(LENGTH(branch_uuid) = 16),
  branch_created enum_boolean NOT NULL DEFAULT 'n',
  branch_deleted enum_boolean NOT NULL DEFAULT 'n',

  zone character varying(255) DEFAULT NULL,
  object_name character varying(255) DEFAULT NULL,
  object_type enum_object_type_all DEFAULT NULL,
  disabled enum_boolean NOT NULL DEFAULT NULL,
  host character varying(255) DEFAULT NULL,
  port d_smallint DEFAULT NULL,
  log_duration character varying(32) DEFAULT NULL,
  apiuser character varying(255) DEFAULT NULL,

  imports TEXT DEFAULT NULL,
  set_null TEXT DEFAULT NULL,
  PRIMARY KEY (branch_uuid, uuid),
  CONSTRAINT icinga_endpoint_branch
      FOREIGN KEY (branch_uuid)
          REFERENCES director_branch (uuid)
          ON DELETE CASCADE
          ON UPDATE CASCADE
);

CREATE UNIQUE INDEX endpoint_branch_object_name ON branched_icinga_endpoint (branch_uuid, object_name);
CREATE INDEX branched_endpoint_search_object_name ON branched_icinga_endpoint (object_name);


CREATE TABLE branched_icinga_service (
  uuid bytea NOT NULL UNIQUE CHECK(LENGTH(uuid) = 16),
  branch_uuid bytea NOT NULL CHECK(LENGTH(branch_uuid) = 16),
  branch_created enum_boolean NOT NULL DEFAULT 'n',
  branch_deleted enum_boolean NOT NULL DEFAULT 'n',

  object_name character varying(255) DEFAULT NULL,
  object_type enum_object_type_all DEFAULT NULL,
  disabled enum_boolean DEFAULT NULL,
  display_name character varying(255) DEFAULT NULL,
  host character varying(255) DEFAULT NULL,
  service_set character varying(255) DEFAULT NULL,
  check_command character varying(255) DEFAULT NULL,
  max_check_attempts integer DEFAULT NULL,
  check_period character varying(255) DEFAULT NULL,
  check_interval character varying(8) DEFAULT NULL,
  retry_interval character varying(8) DEFAULT NULL,
  check_timeout smallint DEFAULT NULL,
  enable_notifications enum_boolean DEFAULT NULL,
  enable_active_checks enum_boolean DEFAULT NULL,
  enable_passive_checks enum_boolean DEFAULT NULL,
  enable_event_handler enum_boolean DEFAULT NULL,
  enable_flapping enum_boolean DEFAULT NULL,
  enable_perfdata enum_boolean DEFAULT NULL,
  event_command character varying(255) DEFAULT NULL,
  flapping_threshold_high smallint DEFAULT NULL,
  flapping_threshold_low smallint DEFAULT NULL,
  volatile enum_boolean DEFAULT NULL,
  zone character varying(255) DEFAULT NULL,
  command_endpoint character varying(255) DEFAULT NULL,
  notes text DEFAULT NULL,
  notes_url character varying(255) DEFAULT NULL,
  action_url character varying(255) DEFAULT NULL,
  icon_image character varying(255) DEFAULT NULL,
  icon_image_alt character varying(255) DEFAULT NULL,
  use_agent enum_boolean DEFAULT NULL,
  apply_for character varying(255) DEFAULT NULL,
  use_var_overrides enum_boolean DEFAULT NULL,
  assign_filter text DEFAULT NULL,
  -- template_choice_id int DEFAULT NULL,

  imports TEXT DEFAULT NULL,
  groups TEXT DEFAULT NULL,
  vars TEXT DEFAULT NULL,
  set_null TEXT DEFAULT NULL,
  PRIMARY KEY (branch_uuid, uuid),
  CONSTRAINT icinga_service_branch
      FOREIGN KEY (branch_uuid)
          REFERENCES director_branch (uuid)
          ON DELETE CASCADE
          ON UPDATE CASCADE
);

CREATE UNIQUE INDEX service_branch_object_name ON branched_icinga_service (branch_uuid, object_name);
CREATE INDEX branched_service_search_object_name ON branched_icinga_service (object_name);
CREATE INDEX branched_service_search_display_name ON branched_icinga_service (display_name);


CREATE TABLE branched_icinga_notification (
  uuid bytea NOT NULL UNIQUE CHECK(LENGTH(uuid) = 16),
  branch_uuid bytea NOT NULL CHECK(LENGTH(branch_uuid) = 16),
  branch_created enum_boolean NOT NULL DEFAULT 'n',
  branch_deleted enum_boolean NOT NULL DEFAULT 'n',

  object_name CHARACTER VARYING(255) DEFAULT NULL,
  object_type enum_object_type_all DEFAULT NULL,
  disabled enum_boolean DEFAULT NULL,
  apply_to enum_host_service DEFAULT NULL,
  host character varying(255) DEFAULT NULL,
  service character varying(255) DEFAULT NULL,
  times_begin integer DEFAULT NULL,
  times_end integer DEFAULT NULL,
  notification_interval integer DEFAULT NULL,
  command character varying(255) DEFAULT NULL,
  period character varying(255) DEFAULT NULL,
  zone character varying(255) DEFAULT NULL,
  assign_filter text DEFAULT NULL,

  states TEXT DEFAULT NULL,
  types TEXT DEFAULT NULL,
  users TEXT DEFAULT NULL,
  usergroups TEXT DEFAULT NULL,

  imports TEXT DEFAULT NULL,
  vars TEXT DEFAULT NULL,
  set_null TEXT DEFAULT NULL,
  PRIMARY KEY (branch_uuid, uuid),
  CONSTRAINT icinga_notification_branch
      FOREIGN KEY (branch_uuid)
          REFERENCES director_branch (uuid)
          ON DELETE CASCADE
          ON UPDATE CASCADE
);

CREATE UNIQUE INDEX notification_branch_object_name ON branched_icinga_notification (branch_uuid, object_name);
CREATE INDEX branched_notification_search_object_name ON branched_icinga_notification (object_name);


CREATE TABLE branched_icinga_scheduled_downtime (
  uuid bytea NOT NULL UNIQUE CHECK(LENGTH(uuid) = 16),
  branch_uuid bytea NOT NULL CHECK(LENGTH(branch_uuid) = 16),
  branch_created enum_boolean NOT NULL DEFAULT 'n',
  branch_deleted enum_boolean NOT NULL DEFAULT 'n',

  object_name character varying(255) DEFAULT NULL,
  zone_id integer DEFAULT NULL,
  object_type enum_object_type_all DEFAULT NULL,
  disabled enum_boolean DEFAULT NULL,
  apply_to enum_host_service DEFAULT NULL,
  assign_filter text DEFAULT NULL,
  author character varying(255) DEFAULT NULL,
  comment text DEFAULT NULL,
  fixed enum_boolean DEFAULT NULL,
  duration int DEFAULT NULL,
  with_services enum_boolean DEFAULT NULL,

  imports TEXT DEFAULT NULL,
  ranges TEXT DEFAULT NULL,
  set_null TEXT DEFAULT NULL,
  PRIMARY KEY (branch_uuid, uuid),
  CONSTRAINT icinga_scheduled_downtime_branch
      FOREIGN KEY (branch_uuid)
          REFERENCES director_branch (uuid)
          ON DELETE CASCADE
          ON UPDATE CASCADE
);

CREATE UNIQUE INDEX scheduled_downtime_branch_object_name ON branched_icinga_scheduled_downtime (branch_uuid, object_name);
CREATE INDEX branched_scheduled_downtime_search_object_name ON branched_icinga_scheduled_downtime (object_name);


CREATE TABLE branched_icinga_dependency (
  uuid bytea NOT NULL UNIQUE CHECK(LENGTH(uuid) = 16),
  branch_uuid bytea NOT NULL CHECK(LENGTH(branch_uuid) = 16),
  branch_created enum_boolean NOT NULL DEFAULT 'n',
  branch_deleted enum_boolean NOT NULL DEFAULT 'n',

  object_name character varying(255) NOT NULL,
  object_type enum_object_type_all NOT NULL,
  disabled enum_boolean DEFAULT 'n',
  apply_to enum_host_service NULL DEFAULT NULL,
  parent_host character varying(255) DEFAULT NULL,
  parent_host_var character varying(128) DEFAULT NULL,
  parent_service character varying(255) DEFAULT NULL,
  child_host character varying(255) DEFAULT NULL,
  child_service character varying(255) DEFAULT NULL,
  disable_checks enum_boolean DEFAULT NULL,
  disable_notifications enum_boolean DEFAULT NULL,
  ignore_soft_states enum_boolean DEFAULT NULL,
  period_id integer DEFAULT NULL,
  zone_id integer DEFAULT NULL,
  assign_filter text DEFAULT NULL,
  parent_service_by_name character varying(255),

  imports TEXT DEFAULT NULL,
  set_null TEXT DEFAULT NULL,
  PRIMARY KEY (branch_uuid, uuid),
  CONSTRAINT icinga_dependency_branch
      FOREIGN KEY (branch_uuid)
          REFERENCES director_branch (uuid)
          ON DELETE CASCADE
          ON UPDATE CASCADE
);

CREATE UNIQUE INDEX dependency_branch_object_name ON branched_icinga_dependency (branch_uuid, object_name);
CREATE INDEX branched_dependency_search_object_name ON branched_icinga_dependency (object_name);


INSERT INTO director_schema_migration
(schema_version, migration_time)
VALUES (175, NOW());
