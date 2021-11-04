CREATE TABLE director_branch (
  uuid VARBINARY(16) NOT NULL,
  owner VARCHAR(255) NOT NULL,
  branch_name VARCHAR(255) NOT NULL,
  description TEXT DEFAULT NULL,
  ts_merge_request BIGINT DEFAULT NULL,
  PRIMARY KEY(uuid),
  UNIQUE KEY (branch_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE director_branch_activity (
  timestamp_ns BIGINT(20) NOT NULL,
  object_uuid VARBINARY(16) NOT NULL,
  branch_uuid VARBINARY(16) NOT NULL,
  action ENUM ('create', 'modify', 'delete') NOT NULL,
  object_table VARCHAR(64) NOT NULL,
  author VARCHAR(255) NOT NULL,
  former_properties LONGTEXT NOT NULL, -- json-encoded
  modified_properties LONGTEXT NOT NULL,
  PRIMARY KEY (timestamp_ns),
  INDEX object_uuid (object_uuid),
  INDEX branch_uuid (branch_uuid),
  CONSTRAINT branch_activity_branch
    FOREIGN KEY branch (branch_uuid)
      REFERENCES director_branch (uuid)
      ON DELETE CASCADE
      ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE branched_icinga_host (
  uuid VARBINARY(16) NOT NULL,
  branch_uuid VARBINARY(16) NOT NULL,
  branch_created ENUM('y', 'n') NOT NULL DEFAULT 'n',
  branch_deleted ENUM('y', 'n') NOT NULL DEFAULT 'n',

  object_name VARCHAR(255) DEFAULT NULL,
  object_type ENUM('object', 'template') DEFAULT NULL,
  disabled ENUM('y', 'n') DEFAULT NULL,
  display_name VARCHAR(255) DEFAULT NULL,
  address VARCHAR(255) DEFAULT NULL,
  address6 VARCHAR(45) DEFAULT NULL,
  check_command VARCHAR(255) DEFAULT NULL,
  max_check_attempts MEDIUMINT UNSIGNED DEFAULT NULL,
  check_period VARCHAR(255) DEFAULT NULL,
  check_interval VARCHAR(8) DEFAULT NULL,
  retry_interval VARCHAR(8) DEFAULT NULL,
  check_timeout SMALLINT UNSIGNED DEFAULT NULL,
  enable_notifications ENUM('y', 'n') DEFAULT NULL,
  enable_active_checks ENUM('y', 'n') DEFAULT NULL,
  enable_passive_checks ENUM('y', 'n') DEFAULT NULL,
  enable_event_handler ENUM('y', 'n') DEFAULT NULL,
  enable_flapping ENUM('y', 'n') DEFAULT NULL,
  enable_perfdata ENUM('y', 'n') DEFAULT NULL,
  event_command VARCHAR(255) DEFAULT NULL,
  flapping_threshold_high SMALLINT UNSIGNED DEFAULT NULL,
  flapping_threshold_low SMALLINT UNSIGNED DEFAULT NULL,
  volatile ENUM('y', 'n') DEFAULT NULL,
  zone VARCHAR(255) DEFAULT NULL,
  command_endpoint VARCHAR(255) DEFAULT NULL,
  notes TEXT DEFAULT NULL,
  notes_url VARCHAR(255) DEFAULT NULL,
  action_url VARCHAR(255) DEFAULT NULL,
  icon_image VARCHAR(255) DEFAULT NULL,
  icon_image_alt VARCHAR(255) DEFAULT NULL,
  has_agent ENUM('y', 'n') DEFAULT NULL,
  master_should_connect ENUM('y', 'n') DEFAULT NULL,
  accept_config ENUM('y', 'n') DEFAULT NULL,
  api_key VARCHAR(40) DEFAULT NULL,

  imports TEXT DEFAULT NULL,
  `groups` TEXT DEFAULT NULL,
  vars MEDIUMTEXT DEFAULT NULL,
  set_null TEXT DEFAULT NULL,
  PRIMARY KEY (branch_uuid, uuid),
  UNIQUE INDEX branch_object_name (branch_uuid, object_name),
  INDEX search_object_name (object_name),
  INDEX search_display_name (display_name),
  CONSTRAINT icinga_host_branch
    FOREIGN KEY branch (branch_uuid)
    REFERENCES director_branch (uuid)
    ON DELETE CASCADE
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE branched_icinga_hostgroup (
  uuid VARBINARY(16) NOT NULL,
  branch_uuid VARBINARY(16) NOT NULL,
  branch_created ENUM('y', 'n') NOT NULL DEFAULT 'n',
  branch_deleted ENUM('y', 'n') NOT NULL DEFAULT 'n',

  object_name VARCHAR(255) DEFAULT NULL,
  object_type ENUM('object', 'template', 'external_object') DEFAULT NULL,
  disabled ENUM('y', 'n') DEFAULT NULL,
  display_name VARCHAR(255) DEFAULT NULL,
  assign_filter TEXT DEFAULT NULL,

  imports TEXT DEFAULT NULL,
  set_null TEXT DEFAULT NULL,
  PRIMARY KEY (branch_uuid, uuid),
  UNIQUE INDEX branch_object_name (branch_uuid, object_name),
  INDEX search_object_name (object_name),
  INDEX search_display_name (display_name),
  CONSTRAINT icinga_hostgroup_branch
    FOREIGN KEY branch (branch_uuid)
    REFERENCES director_branch (uuid)
    ON DELETE CASCADE
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE branched_icinga_servicegroup (
  uuid VARBINARY(16) NOT NULL,
  branch_uuid VARBINARY(16) NOT NULL,
  branch_created ENUM('y', 'n') NOT NULL DEFAULT 'n',
  branch_deleted ENUM('y', 'n') NOT NULL DEFAULT 'n',

  object_name VARCHAR(255) DEFAULT NULL,
  object_type ENUM('object', 'template', 'external_object') DEFAULT NULL,
  disabled ENUM('y', 'n') DEFAULT NULL,
  display_name VARCHAR(255) DEFAULT NULL,
  assign_filter TEXT DEFAULT NULL,

  imports TEXT DEFAULT NULL,
  set_null TEXT DEFAULT NULL,
  PRIMARY KEY (branch_uuid, uuid),
  UNIQUE INDEX branch_object_name (branch_uuid, object_name),
  INDEX search_object_name (object_name),
  INDEX search_display_name (display_name),
  CONSTRAINT icinga_servicegroup_branch
    FOREIGN KEY branch (branch_uuid)
    REFERENCES director_branch (uuid)
    ON DELETE CASCADE
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE branched_icinga_usergroup (
  uuid VARBINARY(16) NOT NULL,
  branch_uuid VARBINARY(16) NOT NULL,
  branch_created ENUM('y', 'n') NOT NULL DEFAULT 'n',
  branch_deleted ENUM('y', 'n') NOT NULL DEFAULT 'n',

  object_name VARCHAR(255) DEFAULT NULL,
  object_type ENUM('object', 'template') DEFAULT NULL,
  disabled ENUM('y', 'n') DEFAULT NULL,
  display_name VARCHAR(255) DEFAULT NULL,

  imports TEXT DEFAULT NULL,
  set_null TEXT DEFAULT NULL,
  PRIMARY KEY (branch_uuid, uuid),
  UNIQUE INDEX branch_object_name (branch_uuid, object_name),
  INDEX search_object_name (object_name),
  INDEX search_display_name (display_name),
  CONSTRAINT icinga_usergroup_branch
    FOREIGN KEY branch (branch_uuid)
    REFERENCES director_branch (uuid)
    ON DELETE CASCADE
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE branched_icinga_user (
  uuid VARBINARY(16) NOT NULL,
  branch_uuid VARBINARY(16) NOT NULL,
  branch_created ENUM('y', 'n') NOT NULL DEFAULT 'n',
  branch_deleted ENUM('y', 'n') NOT NULL DEFAULT 'n',

  object_name VARCHAR(255) DEFAULT NULL,
  object_type ENUM('object', 'template') DEFAULT NULL,
  disabled ENUM('y', 'n') DEFAULT NULL,
  display_name VARCHAR(255) DEFAULT NULL,
  email VARCHAR(255) DEFAULT NULL,
  pager VARCHAR(255) DEFAULT NULL,
  enable_notifications ENUM('y', 'n') DEFAULT NULL,
  period VARCHAR(255) DEFAULT NULL,
  zone VARCHAR(255) DEFAULT NULL,
  states TEXT DEFAULT NULL,
  types TEXT DEFAULT NULL,

  imports TEXT DEFAULT NULL,
  `groups` TEXT DEFAULT NULL,
  vars MEDIUMTEXT DEFAULT NULL,
  set_null TEXT DEFAULT NULL,
  PRIMARY KEY (branch_uuid, uuid),
  UNIQUE INDEX branch_object_name (branch_uuid, object_name),
  INDEX search_object_name (object_name),
  INDEX search_display_name (display_name),
  CONSTRAINT icinga_user_branch
    FOREIGN KEY branch (branch_uuid)
    REFERENCES director_branch (uuid)
    ON DELETE CASCADE
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE branched_icinga_zone (
  uuid VARBINARY(16) NOT NULL,
  branch_uuid VARBINARY(16) NOT NULL,
  branch_created ENUM('y', 'n') NOT NULL DEFAULT 'n',
  branch_deleted ENUM('y', 'n') NOT NULL DEFAULT 'n',

  object_name VARCHAR(255) DEFAULT NULL,
  parent VARCHAR(255) DEFAULT NULL,
  object_type ENUM('object', 'template', 'external_object') DEFAULT NULL,
  disabled ENUM('y', 'n') DEFAULT NULL,
  is_global ENUM('y', 'n') DEFAULT NULL,

  imports TEXT DEFAULT NULL,
  set_null TEXT DEFAULT NULL,
  PRIMARY KEY (branch_uuid, uuid),
  UNIQUE INDEX branch_object_name (branch_uuid, object_name),
  INDEX search_object_name (object_name),
  CONSTRAINT icinga_zone_branch
    FOREIGN KEY branch (branch_uuid)
    REFERENCES director_branch (uuid)
    ON DELETE CASCADE
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE branched_icinga_timeperiod (
  uuid VARBINARY(16) NOT NULL,
  branch_uuid VARBINARY(16) NOT NULL,
  branch_created ENUM('y', 'n') NOT NULL DEFAULT 'n',
  branch_deleted ENUM('y', 'n') NOT NULL DEFAULT 'n',

  object_name VARCHAR(255) DEFAULT NULL,
  object_type ENUM('object', 'template') DEFAULT NULL,
  disabled ENUM('y', 'n') DEFAULT NULL,
  display_name VARCHAR(255) DEFAULT NULL,
  update_method VARCHAR(64) DEFAULT NULL COMMENT 'Usually LegacyTimePeriod',
  zone VARCHAR(255) DEFAULT NULL,
  prefer_includes ENUM('y', 'n') DEFAULT NULL,

  imports TEXT DEFAULT NULL,
  ranges TEXT DEFAULT NULL,
  set_null TEXT DEFAULT NULL,
  PRIMARY KEY (branch_uuid, uuid),
  UNIQUE INDEX branch_object_name (branch_uuid, object_name),
  INDEX search_object_name (object_name),
  INDEX search_display_name (display_name),
  CONSTRAINT icinga_timeperiod_branch
    FOREIGN KEY branch (branch_uuid)
    REFERENCES director_branch (uuid)
    ON DELETE CASCADE
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE branched_icinga_command (
  uuid VARBINARY(16) NOT NULL,
  branch_uuid VARBINARY(16) NOT NULL,
  branch_created ENUM('y', 'n') NOT NULL DEFAULT 'n',
  branch_deleted ENUM('y', 'n') NOT NULL DEFAULT 'n',

  object_name VARCHAR(255) DEFAULT NULL,
  object_type ENUM('object', 'template', 'external_object') DEFAULT NULL,
  disabled ENUM('y', 'n') DEFAULT NULL,
  methods_execute VARCHAR(64) DEFAULT NULL,
  command TEXT DEFAULT NULL,
  is_string ENUM('y', 'n') NULL,
  timeout SMALLINT UNSIGNED DEFAULT NULL,
  zone VARCHAR(255) DEFAULT NULL,

  imports TEXT DEFAULT NULL,
  arguments TEXT DEFAULT NULL,
  set_null TEXT DEFAULT NULL,
  PRIMARY KEY (branch_uuid, uuid),
  UNIQUE INDEX branch_object_name (branch_uuid, object_name),
  INDEX search_object_name (object_name),
  CONSTRAINT icinga_command_branch
    FOREIGN KEY branch (branch_uuid)
    REFERENCES director_branch (uuid)
    ON DELETE CASCADE
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE branched_icinga_apiuser (
  uuid VARBINARY(16) NOT NULL,
  branch_uuid VARBINARY(16) NOT NULL,
  branch_created ENUM('y', 'n') NOT NULL DEFAULT 'n',
  branch_deleted ENUM('y', 'n') NOT NULL DEFAULT 'n',

  object_name VARCHAR(255) DEFAULT NULL,
  object_type ENUM('object', 'template', 'external_object') DEFAULT NULL,
  disabled ENUM('y', 'n') DEFAULT NULL,
  password VARCHAR(255) DEFAULT NULL,
  client_dn VARCHAR(64) DEFAULT NULL,
  permissions TEXT DEFAULT NULL COMMENT 'JSON-encoded permissions',

  set_null TEXT DEFAULT NULL,
  PRIMARY KEY (branch_uuid, uuid),
  UNIQUE INDEX branch_object_name (branch_uuid, object_name),
  INDEX search_object_name (object_name),
  CONSTRAINT icinga_apiuser_branch
    FOREIGN KEY branch (branch_uuid)
    REFERENCES director_branch (uuid)
    ON DELETE CASCADE
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE branched_icinga_endpoint (
  uuid VARBINARY(16) NOT NULL,
  branch_uuid VARBINARY(16) NOT NULL,
  branch_created ENUM('y', 'n') NOT NULL DEFAULT 'n',
  branch_deleted ENUM('y', 'n') NOT NULL DEFAULT 'n',

  object_name VARCHAR(255) DEFAULT NULL,
  object_type ENUM('object', 'template', 'external_object') DEFAULT NULL,
  disabled ENUM('y', 'n') DEFAULT NULL,
  zone VARCHAR(255) DEFAULT NULL,
  host VARCHAR(255) DEFAULT NULL,
  port SMALLINT UNSIGNED DEFAULT NULL,
  log_duration VARCHAR(32) DEFAULT NULL,
  apiuser VARCHAR(255) DEFAULT NULL,

  imports TEXT DEFAULT NULL,
  set_null TEXT DEFAULT NULL,
  PRIMARY KEY (branch_uuid, uuid),
  UNIQUE INDEX branch_object_name (branch_uuid, object_name),
  INDEX search_object_name (object_name),
  CONSTRAINT icinga_endpoint_branch
    FOREIGN KEY branch (branch_uuid)
    REFERENCES director_branch (uuid)
    ON DELETE CASCADE
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE branched_icinga_service (
  uuid VARBINARY(16) NOT NULL,
  branch_uuid VARBINARY(16) NOT NULL,
  branch_created ENUM('y', 'n') NOT NULL DEFAULT 'n',
  branch_deleted ENUM('y', 'n') NOT NULL DEFAULT 'n',

  object_name VARCHAR(255) DEFAULT NULL,
  object_type ENUM('object', 'template', 'apply') DEFAULT NULL,
  disabled ENUM('y', 'n') DEFAULT NULL,
  display_name VARCHAR(255) DEFAULT NULL,
  host VARCHAR(255) DEFAULT NULL,
  service_set VARCHAR(255) DEFAULT NULL,
  check_command VARCHAR(255) DEFAULT NULL,
  max_check_attempts MEDIUMINT UNSIGNED DEFAULT NULL,
  check_period VARCHAR(255) DEFAULT NULL,
  check_interval VARCHAR(8) DEFAULT NULL,
  retry_interval VARCHAR(8) DEFAULT NULL,
  check_timeout SMALLINT UNSIGNED DEFAULT NULL,
  enable_notifications ENUM('y', 'n') DEFAULT NULL,
  enable_active_checks ENUM('y', 'n') DEFAULT NULL,
  enable_passive_checks ENUM('y', 'n') DEFAULT NULL,
  enable_event_handler ENUM('y', 'n') DEFAULT NULL,
  enable_flapping ENUM('y', 'n') DEFAULT NULL,
  enable_perfdata ENUM('y', 'n') DEFAULT NULL,
  event_command VARCHAR(255) DEFAULT NULL,
  flapping_threshold_high SMALLINT UNSIGNED DEFAULT NULL,
  flapping_threshold_low SMALLINT UNSIGNED DEFAULT NULL,
  volatile ENUM('y', 'n') DEFAULT NULL,
  zone VARCHAR(255) DEFAULT NULL,
  command_endpoint VARCHAR(255) DEFAULT NULL,
  notes TEXT DEFAULT NULL,
  notes_url VARCHAR(255) DEFAULT NULL,
  action_url VARCHAR(255) DEFAULT NULL,
  icon_image VARCHAR(255) DEFAULT NULL,
  icon_image_alt VARCHAR(255) DEFAULT NULL,
  use_agent ENUM('y', 'n') DEFAULT NULL,
  apply_for VARCHAR(255) DEFAULT NULL,
  use_var_overrides ENUM('y', 'n') DEFAULT NULL,
  assign_filter TEXT DEFAULT NULL,
  -- template_choice VARCHAR(255) DEFAULT NULL,

  imports TEXT DEFAULT NULL,
  `groups` TEXT DEFAULT NULL,
  vars MEDIUMTEXT DEFAULT NULL,
  set_null TEXT DEFAULT NULL,
  PRIMARY KEY (branch_uuid, uuid),
  INDEX search_object_name (object_name),
  INDEX search_display_name (display_name),
  CONSTRAINT icinga_service_branch
    FOREIGN KEY branch (branch_uuid)
    REFERENCES director_branch (uuid)
    ON DELETE CASCADE
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE branched_icinga_notification (
  uuid VARBINARY(16) NOT NULL,
  branch_uuid VARBINARY(16) NOT NULL,
  branch_created ENUM('y', 'n') NOT NULL DEFAULT 'n',
  branch_deleted ENUM('y', 'n') NOT NULL DEFAULT 'n',

  object_name VARCHAR(255) DEFAULT NULL,
  object_type ENUM('object', 'template', 'apply') DEFAULT NULL,
  disabled ENUM('y', 'n') DEFAULT NULL,
  apply_to ENUM('host', 'service') DEFAULT NULL,
  host VARCHAR(255) DEFAULT NULL,
  service VARCHAR(255) DEFAULT NULL,
  times_begin INT(10) UNSIGNED DEFAULT NULL,
  times_end INT(10) UNSIGNED DEFAULT NULL,
  notification_interval INT(10) UNSIGNED DEFAULT NULL,
  command VARCHAR(255) DEFAULT NULL,
  period VARCHAR(255) DEFAULT NULL,
  zone VARCHAR(255) DEFAULT NULL,
  assign_filter TEXT DEFAULT NULL,

  states TEXT DEFAULT NULL,
  types TEXT DEFAULT NULL,
  users TEXT DEFAULT NULL,
  usergroups TEXT DEFAULT NULL,

  imports TEXT DEFAULT NULL,
  vars MEDIUMTEXT DEFAULT NULL,
  set_null TEXT DEFAULT NULL,
  PRIMARY KEY (branch_uuid, uuid),
  UNIQUE INDEX branch_object_name (branch_uuid, object_name),
  INDEX search_object_name (object_name),
  CONSTRAINT icinga_notification_branch
    FOREIGN KEY branch (branch_uuid)
    REFERENCES director_branch (uuid)
    ON DELETE CASCADE
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE branched_icinga_scheduled_downtime (
  uuid VARBINARY(16) NOT NULL,
  branch_uuid VARBINARY(16) NOT NULL,
  branch_created ENUM('y', 'n') NOT NULL DEFAULT 'n',
  branch_deleted ENUM('y', 'n') NOT NULL DEFAULT 'n',

  object_name VARCHAR(255) DEFAULT NULL,
  zone VARCHAR(255) DEFAULT NULL,
  object_type ENUM('object', 'template', 'apply') DEFAULT NULL,
  disabled ENUM('y', 'n') DEFAULT NULL,
  apply_to ENUM('host', 'service') DEFAULT NULL,
  assign_filter TEXT DEFAULT NULL,
  author VARCHAR(255) DEFAULT NULL,
  comment TEXT DEFAULT NULL,
  fixed ENUM('y', 'n') DEFAULT NULL,
  duration INT(10) UNSIGNED DEFAULT NULL,
  with_services ENUM('y', 'n') NULL DEFAULT NULL,

  imports TEXT DEFAULT NULL,
  ranges TEXT DEFAULT NULL,
  set_null TEXT DEFAULT NULL,
  PRIMARY KEY (branch_uuid, uuid),
  UNIQUE INDEX branch_object_name (branch_uuid, object_name),
  INDEX search_object_name (object_name),
  CONSTRAINT icinga_scheduled_downtime_branch
    FOREIGN KEY branch (branch_uuid)
    REFERENCES director_branch (uuid)
    ON DELETE CASCADE
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE branched_icinga_dependency (
  uuid VARBINARY(16) NOT NULL,
  branch_uuid VARBINARY(16) NOT NULL,
  branch_created ENUM('y', 'n') NOT NULL DEFAULT 'n',
  branch_deleted ENUM('y', 'n') NOT NULL DEFAULT 'n',

  object_name VARCHAR(255) DEFAULT NULL,
  object_type ENUM('object', 'template', 'apply') DEFAULT NULL,
  disabled ENUM('y', 'n') DEFAULT NULL,
  apply_to ENUM('host', 'service') DEFAULT NULL,
  parent_host VARCHAR(255) DEFAULT NULL,
  parent_host_var VARCHAR(128) DEFAULT NULL,
  parent_service VARCHAR(255) DEFAULT NULL,
  child_host VARCHAR(255) DEFAULT NULL,
  child_service VARCHAR(255) DEFAULT NULL,
  disable_checks ENUM('y', 'n') DEFAULT NULL,
  disable_notifications ENUM('y', 'n') DEFAULT NULL,
  ignore_soft_states ENUM('y', 'n') DEFAULT NULL,
  period VARCHAR(255) DEFAULT NULL,
  zone VARCHAR(255) DEFAULT NULL,
  assign_filter TEXT DEFAULT NULL,
  parent_service_by_name VARCHAR(255) DEFAULT NULL,

  imports TEXT DEFAULT NULL,
  set_null TEXT DEFAULT NULL,
  PRIMARY KEY (branch_uuid, uuid),
  UNIQUE INDEX branch_object_name (branch_uuid, object_name),
  INDEX search_object_name (object_name),
  CONSTRAINT icinga_dependency_branch
    FOREIGN KEY branch (branch_uuid)
    REFERENCES director_branch (uuid)
    ON DELETE CASCADE
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

INSERT INTO director_schema_migration
  (schema_version, migration_time)
  VALUES (175, NOW());
