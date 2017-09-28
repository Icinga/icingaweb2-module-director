--
-- MySQL schema
-- ============
--
-- You should normally not be required to care about schema handling.
-- Director does all the migrations for you and guides you either in
-- the frontend or provides everything you need for automated migration
-- handling. Please find more related information in our documentation.

SET sql_mode = 'STRICT_ALL_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION,PIPES_AS_CONCAT,ANSI_QUOTES,ERROR_FOR_DIVISION_BY_ZERO,NO_AUTO_CREATE_USER';

CREATE TABLE director_activity_log (
  id BIGINT(20) UNSIGNED AUTO_INCREMENT NOT NULL,
  object_type VARCHAR(64) NOT NULL,
  object_name VARCHAR(255) NOT NULL,
  action_name ENUM('create', 'delete', 'modify') NOT NULL,
  old_properties TEXT DEFAULT NULL COMMENT 'Property hash, JSON',
  new_properties TEXT DEFAULT NULL COMMENT 'Property hash, JSON',
  author VARCHAR(64) NOT NULL,
  change_time DATETIME NOT NULL,
  checksum VARBINARY(20) NOT NULL,
  parent_checksum VARBINARY(20) DEFAULT NULL,
  PRIMARY KEY (id),
  INDEX sort_idx (change_time),
  INDEX search_idx (object_name),
  INDEX search_idx2 (object_type(32), object_name(64), change_time),
  INDEX search_author (author),
  INDEX checksum (checksum)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE director_generated_config (
  checksum VARBINARY(20) NOT NULL COMMENT 'SHA1(last_activity_checksum;file_path=checksum;file_path=checksum;...)',
  director_version VARCHAR(64) DEFAULT NULL,
  director_db_version INT(10) DEFAULT NULL,
  duration INT(10) UNSIGNED DEFAULT NULL COMMENT 'Config generation duration (ms)',
  first_activity_checksum VARBINARY(20) NOT NULL,
  last_activity_checksum VARBINARY(20) NOT NULL,
  PRIMARY KEY (checksum),
  CONSTRAINT director_generated_config_activity
    FOREIGN KEY activity_checksum (last_activity_checksum)
    REFERENCES director_activity_log (checksum)
    ON DELETE RESTRICT
    ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE director_generated_file (
  checksum VARBINARY(20) NOT NULL COMMENT 'SHA1(content)',
  content MEDIUMTEXT NOT NULL,
  cnt_object INT(10) UNSIGNED NOT NULL DEFAULT 0,
  cnt_template INT(10) UNSIGNED NOT NULL DEFAULT 0,
  cnt_apply INT(10) UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (checksum)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE director_generated_config_file (
  config_checksum VARBINARY(20) NOT NULL,
  file_checksum VARBINARY(20) NOT NULL,
  file_path VARCHAR(128) NOT NULL COMMENT 'e.g. zones/nafta/hosts.conf',
  CONSTRAINT director_generated_config_file_config
    FOREIGN KEY config (config_checksum)
    REFERENCES director_generated_config (checksum)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT director_generated_config_file_file
    FOREIGN KEY checksum (file_checksum)
    REFERENCES director_generated_file (checksum)
    ON DELETE RESTRICT
    ON UPDATE RESTRICT,
  PRIMARY KEY (config_checksum, file_path),
  INDEX search_idx (file_checksum)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE director_deployment_log (
  id BIGINT(20) UNSIGNED AUTO_INCREMENT NOT NULL,
  config_checksum VARBINARY(20) DEFAULT NULL,
  last_activity_checksum VARBINARY(20) NOT NULL,
  peer_identity VARCHAR(64) NOT NULL,
  start_time DATETIME NOT NULL,
  end_time DATETIME DEFAULT NULL,
  abort_time DATETIME DEFAULT NULL,
  duration_connection INT(10) UNSIGNED DEFAULT NULL
    COMMENT 'The time it took to connect to an Icinga node (ms)',
  duration_dump INT(10) UNSIGNED DEFAULT NULL
    COMMENT 'Time spent dumping the config (ms)',
  stage_name VARCHAR(96) DEFAULT NULL,
  stage_collected ENUM('y', 'n') DEFAULT NULL,
  connection_succeeded ENUM('y', 'n') DEFAULT NULL,
  dump_succeeded ENUM('y', 'n') DEFAULT NULL,
  startup_succeeded ENUM('y', 'n') DEFAULT NULL,
  username VARCHAR(64) DEFAULT NULL COMMENT 'The user that triggered this deployment',
  startup_log MEDIUMTEXT DEFAULT NULL,
  PRIMARY KEY (id),
  CONSTRAINT config_checksum
    FOREIGN KEY config_checksum (config_checksum)
    REFERENCES director_generated_config (checksum)
    ON DELETE SET NULL
    ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE director_datalist (
  id INT(10) UNSIGNED AUTO_INCREMENT NOT NULL,
  list_name VARCHAR(255) NOT NULL,
  owner VARCHAR(255) NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY list_name (list_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE director_datalist_entry (
  list_id INT(10) UNSIGNED NOT NULL,
  entry_name VARCHAR(255) COLLATE utf8_bin NOT NULL,
  entry_value TEXT DEFAULT NULL,
  format enum ('string', 'expression', 'json'),
  allowed_roles VARCHAR(255) DEFAULT NULL,
  PRIMARY KEY (list_id, entry_name),
  CONSTRAINT director_datalist_value_datalist
    FOREIGN KEY datalist (list_id)
    REFERENCES director_datalist (id)
    ON DELETE CASCADE
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE director_datafield (
  id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  varname VARCHAR(64) NOT NULL COLLATE utf8_bin,
  caption VARCHAR(255) NOT NULL,
  description TEXT DEFAULT NULL,
  datatype varchar(255) NOT NULL,
-- datatype_param? multiple ones?
  format enum ('string', 'json', 'expression'),
  PRIMARY KEY (id),
  KEY search_idx (varname)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE director_datafield_setting (
  datafield_id INT(10) UNSIGNED NOT NULL,
  setting_name VARCHAR(64) NOT NULL,
  setting_value TEXT NOT NULL,
  PRIMARY KEY (datafield_id, setting_name),
  CONSTRAINT datafield_id_settings
  FOREIGN KEY datafield (datafield_id)
  REFERENCES director_datafield (id)
    ON DELETE CASCADE
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE director_schema_migration (
  schema_version SMALLINT UNSIGNED NOT NULL,
  migration_time DATETIME NOT NULL,
  PRIMARY KEY(schema_version)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE director_setting (
  setting_name VARCHAR(64) NOT NULL,
  setting_value VARCHAR(255) NOT NULL,
  PRIMARY KEY(setting_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE icinga_zone (
  id INT(10) UNSIGNED AUTO_INCREMENT NOT NULL,
  parent_id INT(10) UNSIGNED DEFAULT NULL,
  object_name VARCHAR(255) NOT NULL,
  object_type ENUM('object', 'template', 'external_object') NOT NULL,
  disabled ENUM('y', 'n') NOT NULL DEFAULT 'n',
  is_global ENUM('y', 'n') NOT NULL DEFAULT 'n',
  PRIMARY KEY (id),
  UNIQUE INDEX object_name (object_name),
  CONSTRAINT icinga_zone_parent
    FOREIGN KEY parent_zone (parent_id)
    REFERENCES icinga_zone (id)
    ON DELETE RESTRICT
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE icinga_zone_inheritance (
  zone_id INT(10) UNSIGNED NOT NULL,
  parent_zone_id INT(10) UNSIGNED NOT NULL,
  weight MEDIUMINT UNSIGNED DEFAULT NULL,
  PRIMARY KEY (zone_id, parent_zone_id),
  UNIQUE KEY unique_order (zone_id, weight),
  CONSTRAINT icinga_zone_inheritance_zone
  FOREIGN KEY zone (zone_id)
  REFERENCES icinga_zone (id)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT icinga_zone_inheritance_parent_zone
  FOREIGN KEY zone (parent_zone_id)
  REFERENCES icinga_zone (id)
    ON DELETE RESTRICT
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE icinga_timeperiod (
  id INT(10) UNSIGNED AUTO_INCREMENT NOT NULL,
  object_name VARCHAR(255) NOT NULL,
  display_name VARCHAR(255) DEFAULT NULL,
  update_method VARCHAR(64) DEFAULT NULL COMMENT 'Usually LegacyTimePeriod',
  zone_id INT(10) UNSIGNED DEFAULT NULL,
  object_type ENUM('object', 'template') NOT NULL,
  disabled ENUM('y', 'n') NOT NULL DEFAULT 'n',
  PRIMARY KEY (id),
  UNIQUE INDEX object_name (object_name, zone_id),
  CONSTRAINT icinga_timeperiod_zone
    FOREIGN KEY zone (zone_id)
    REFERENCES icinga_zone (id)
    ON DELETE RESTRICT
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE icinga_timeperiod_inheritance (
  timeperiod_id INT(10) UNSIGNED NOT NULL,
  parent_timeperiod_id INT(10) UNSIGNED NOT NULL,
  weight MEDIUMINT UNSIGNED DEFAULT NULL,
  PRIMARY KEY (timeperiod_id, parent_timeperiod_id),
  UNIQUE KEY unique_order (timeperiod_id, weight),
  CONSTRAINT icinga_timeperiod_inheritance_timeperiod
  FOREIGN KEY host (timeperiod_id)
  REFERENCES icinga_timeperiod (id)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT icinga_timeperiod_inheritance_parent_timeperiod
  FOREIGN KEY host (parent_timeperiod_id)
  REFERENCES icinga_timeperiod (id)
    ON DELETE RESTRICT
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE icinga_timeperiod_range (
  timeperiod_id INT(10) UNSIGNED AUTO_INCREMENT NOT NULL,
  range_key VARCHAR(255) NOT NULL COMMENT 'monday, ...',
  range_value VARCHAR(255) NOT NULL COMMENT '00:00-24:00, ...',
  range_type ENUM('include', 'exclude') NOT NULL DEFAULT 'include'
    COMMENT 'include -> ranges {}, exclude ranges_ignore {} - not yet',
  merge_behaviour ENUM('set', 'add', 'substract') NOT NULL DEFAULT 'set'
    COMMENT 'set -> = {}, add -> += {}, substract -> -= {}',
  PRIMARY KEY (timeperiod_id, range_type, range_key),
  CONSTRAINT icinga_timeperiod_range_timeperiod
    FOREIGN KEY timeperiod (timeperiod_id)
    REFERENCES icinga_timeperiod (id)
    ON DELETE CASCADE
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE director_job (
  id INT(10) UNSIGNED AUTO_INCREMENT NOT NULL,
  job_name VARCHAR(64) NOT NULL,
  job_class VARCHAR(72) NOT NULL,
  disabled ENUM('y', 'n') NOT NULL DEFAULT 'n',
  run_interval INT(10) UNSIGNED NOT NULL, -- seconds
  timeperiod_id INT(10) UNSIGNED DEFAULT NULL,
  last_attempt_succeeded ENUM('y', 'n') DEFAULT NULL,
  ts_last_attempt DATETIME DEFAULT NULL,
  ts_last_error DATETIME DEFAULT NULL,
  last_error_message TEXT DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY (job_name),
  CONSTRAINT director_job_period
    FOREIGN KEY timeperiod (timeperiod_id)
    REFERENCES icinga_timeperiod (id)
    ON DELETE RESTRICT
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE director_job_setting (
  job_id INT UNSIGNED NOT NULL,
  setting_name VARCHAR(64) NOT NULL,
  setting_value TEXT DEFAULT NULL,
  PRIMARY KEY (job_id, setting_name),
  CONSTRAINT job_settings
    FOREIGN KEY director_job (job_id)
    REFERENCES director_job (id)
    ON DELETE CASCADE
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE icinga_command (
  id INT(10) UNSIGNED AUTO_INCREMENT NOT NULL,
  object_name VARCHAR(255) NOT NULL,
  object_type ENUM('object', 'template', 'external_object') NOT NULL
    COMMENT 'external_object is an attempt to work with existing commands',
  disabled ENUM('y', 'n') NOT NULL DEFAULT 'n',
  methods_execute VARCHAR(64) DEFAULT NULL,
  command TEXT DEFAULT NULL,
  -- env text DEFAULT NULL,
  -- vars text DEFAULT NULL,
  timeout SMALLINT UNSIGNED DEFAULT NULL,
  zone_id INT(10) UNSIGNED DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE INDEX object_name (object_name, zone_id),
  CONSTRAINT icinga_command_zone
    FOREIGN KEY zone (zone_id)
    REFERENCES icinga_zone (id)
    ON DELETE RESTRICT
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE icinga_command_inheritance (
  command_id INT(10) UNSIGNED NOT NULL,
  parent_command_id INT(10) UNSIGNED NOT NULL,
  weight MEDIUMINT UNSIGNED DEFAULT NULL,
  PRIMARY KEY (command_id, parent_command_id),
  UNIQUE KEY unique_order (command_id, weight),
  CONSTRAINT icinga_command_inheritance_command
  FOREIGN KEY command (command_id)
  REFERENCES icinga_command (id)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT icinga_command_inheritance_parent_command
  FOREIGN KEY command (parent_command_id)
  REFERENCES icinga_command (id)
    ON DELETE RESTRICT
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE icinga_command_argument (
  id INT(10) UNSIGNED AUTO_INCREMENT NOT NULL,
  command_id INT(10) UNSIGNED NOT NULL,
  argument_name VARCHAR(64) COLLATE utf8_bin NOT NULL COMMENT '-x, --host',
  argument_value TEXT DEFAULT NULL,
  argument_format ENUM('string', 'expression', 'json') NULL DEFAULT NULL,
  key_string VARCHAR(64) DEFAULT NULL COMMENT 'Overrides name',
  description TEXT DEFAULT NULL,
  skip_key ENUM('y', 'n') DEFAULT NULL,
  set_if VARCHAR(255) DEFAULT NULL, -- (string expression, must resolve to a numeric value)
  set_if_format ENUM('string', 'expression', 'json') DEFAULT NULL,
  sort_order SMALLINT DEFAULT NULL, -- -> order
  repeat_key ENUM('y', 'n') DEFAULT NULL COMMENT 'Useful with array values',
  required ENUM('y', 'n') DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY unique_idx (command_id, argument_name),
  INDEX sort_idx (command_id, sort_order),
  CONSTRAINT icinga_command_argument_command
    FOREIGN KEY command (command_id)
    REFERENCES icinga_command (id)
    ON DELETE CASCADE
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE icinga_command_field (
  command_id INT(10) UNSIGNED NOT NULL,
  datafield_id INT(10) UNSIGNED NOT NULL,
  is_required ENUM('y', 'n') NOT NULL,
  var_filter TEXT DEFAULT NULL,
  PRIMARY KEY (command_id, datafield_id),
  CONSTRAINT icinga_command_field_command
  FOREIGN KEY command_id (command_id)
    REFERENCES icinga_command (id)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT icinga_command_field_datafield
  FOREIGN KEY datafield(datafield_id)
  REFERENCES director_datafield (id)
    ON DELETE CASCADE
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE icinga_command_var (
  command_id INT(10) UNSIGNED NOT NULL,
  varname VARCHAR(255) NOT NULL COLLATE utf8_bin,
  varvalue TEXT DEFAULT NULL,
  format ENUM('string', 'expression', 'json') NOT NULL DEFAULT 'string',
  checksum VARBINARY(20) DEFAULT NULL,
  PRIMARY KEY (command_id, varname),
  INDEX search_idx (varname),
  INDEX checksum (checksum),
  CONSTRAINT icinga_command_var_command
    FOREIGN KEY command (command_id)
    REFERENCES icinga_command (id)
    ON DELETE CASCADE
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE icinga_apiuser (
  id INT(10) UNSIGNED AUTO_INCREMENT NOT NULL,
  object_name VARCHAR(255) NOT NULL,
  object_type ENUM('object', 'template', 'external_object') NOT NULL,
  disabled ENUM('y', 'n') NOT NULL DEFAULT 'n',
  password VARCHAR(255) DEFAULT NULL,
  client_dn VARCHAR(64) DEFAULT NULL,
  permissions TEXT DEFAULT NULL COMMENT 'JSON-encoded permissions',
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE icinga_endpoint (
  id INT(10) UNSIGNED AUTO_INCREMENT NOT NULL,
  zone_id INT(10) UNSIGNED DEFAULT NULL,
  object_name VARCHAR(255) NOT NULL,
  object_type ENUM('object', 'template', 'external_object') NOT NULL,
  disabled ENUM('y', 'n') NOT NULL DEFAULT 'n',
  host VARCHAR(255) DEFAULT NULL COMMENT 'IP address / hostname of remote node',
  port SMALLINT UNSIGNED DEFAULT NULL COMMENT '5665 if not set',
  log_duration VARCHAR(32) DEFAULT NULL COMMENT '1d if not set',
  apiuser_id INT(10) UNSIGNED DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE INDEX object_name (object_name),
  CONSTRAINT icinga_endpoint_zone
    FOREIGN KEY zone (zone_id)
    REFERENCES icinga_zone (id)
    ON DELETE RESTRICT
    ON UPDATE CASCADE,
  CONSTRAINT icinga_apiuser
    FOREIGN KEY apiuser (apiuser_id)
    REFERENCES icinga_apiuser (id)
    ON DELETE RESTRICT
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE icinga_endpoint_inheritance (
  endpoint_id INT(10) UNSIGNED NOT NULL,
  parent_endpoint_id INT(10) UNSIGNED NOT NULL,
  weight MEDIUMINT UNSIGNED DEFAULT NULL,
  PRIMARY KEY (endpoint_id, parent_endpoint_id),
  UNIQUE KEY unique_order (endpoint_id, weight),
  CONSTRAINT icinga_endpoint_inheritance_endpoint
  FOREIGN KEY endpoint (endpoint_id)
  REFERENCES icinga_endpoint (id)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT icinga_endpoint_inheritance_parent_endpoint
  FOREIGN KEY endpoint (parent_endpoint_id)
  REFERENCES icinga_endpoint (id)
    ON DELETE RESTRICT
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE icinga_host_template_choice (
  id INT(10) UNSIGNED AUTO_INCREMENT NOT NULL,
  object_name VARCHAR(64) NOT NULL,
  description TEXT DEFAULT NULL,
  min_required SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  max_allowed SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  required_template_id INT(10) UNSIGNED DEFAULT NULL,
  allowed_roles VARCHAR(255) DEFAULT NULL,
  CONSTRAINT host_template_choice_required_template
  FOREIGN KEY required_template (required_template_id)
  REFERENCES icinga_host (id)
    ON DELETE RESTRICT
    ON UPDATE CASCADE,
  PRIMARY KEY (id),
  UNIQUE KEY (object_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE icinga_host (
  id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  object_name VARCHAR(255) NOT NULL,
  object_type ENUM('object', 'template') NOT NULL,
  disabled ENUM('y', 'n') NOT NULL DEFAULT 'n',
  display_name VARCHAR(255) DEFAULT NULL,
  address VARCHAR(64) DEFAULT NULL,
  address6 VARCHAR(45) DEFAULT NULL,
  check_command_id INT(10) UNSIGNED DEFAULT NULL,
  max_check_attempts MEDIUMINT UNSIGNED DEFAULT NULL,
  check_period_id INT(10) UNSIGNED DEFAULT NULL,
  check_interval VARCHAR(8) DEFAULT NULL,
  retry_interval VARCHAR(8) DEFAULT NULL,
  check_timeout SMALLINT UNSIGNED DEFAULT NULL,
  enable_notifications ENUM('y', 'n') DEFAULT NULL,
  enable_active_checks ENUM('y', 'n') DEFAULT NULL,
  enable_passive_checks ENUM('y', 'n') DEFAULT NULL,
  enable_event_handler ENUM('y', 'n') DEFAULT NULL,
  enable_flapping ENUM('y', 'n') DEFAULT NULL,
  enable_perfdata ENUM('y', 'n') DEFAULT NULL,
  event_command_id INT(10) UNSIGNED DEFAULT NULL,
  flapping_threshold SMALLINT UNSIGNED default null,
  volatile ENUM('y', 'n') DEFAULT NULL,
  zone_id INT(10) UNSIGNED DEFAULT NULL,
  command_endpoint_id INT(10) UNSIGNED DEFAULT NULL,
  notes TEXT DEFAULT NULL,
  notes_url VARCHAR(255) DEFAULT NULL,
  action_url VARCHAR(255) DEFAULT NULL,
  icon_image VARCHAR(255) DEFAULT NULL,
  icon_image_alt VARCHAR(255) DEFAULT NULL,
  has_agent ENUM('y', 'n') DEFAULT NULL,
  master_should_connect ENUM('y', 'n') DEFAULT NULL,
  accept_config ENUM('y', 'n') DEFAULT NULL,
  api_key VARCHAR(40) DEFAULT NULL,
  template_choice_id INT(10) UNSIGNED DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE INDEX object_name (object_name),
  UNIQUE INDEX api_key (api_key),
  KEY search_idx (display_name),
  CONSTRAINT icinga_host_zone
    FOREIGN KEY zone (zone_id)
    REFERENCES icinga_zone (id)
    ON DELETE RESTRICT
    ON UPDATE CASCADE,
  CONSTRAINT icinga_host_check_period
    FOREIGN KEY timeperiod (check_period_id)
    REFERENCES icinga_timeperiod (id)
    ON DELETE RESTRICT
    ON UPDATE CASCADE,
  CONSTRAINT icinga_host_check_command
    FOREIGN KEY check_command (check_command_id)
    REFERENCES icinga_command (id)
    ON DELETE RESTRICT
    ON UPDATE CASCADE,
  CONSTRAINT icinga_host_event_command
    FOREIGN KEY event_command (event_command_id)
    REFERENCES icinga_command (id)
    ON DELETE RESTRICT
    ON UPDATE CASCADE,
  CONSTRAINT icinga_host_command_endpoint
    FOREIGN KEY command_endpoint (command_endpoint_id)
    REFERENCES icinga_endpoint (id)
    ON DELETE RESTRICT
    ON UPDATE CASCADE,
  CONSTRAINT icinga_host_template_choice
    FOREIGN KEY choice (template_choice_id)
    REFERENCES icinga_host_template_choice (id)
    ON DELETE SET NULL
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE icinga_host_inheritance (
  host_id INT(10) UNSIGNED NOT NULL,
  parent_host_id INT(10) UNSIGNED NOT NULL,
  weight MEDIUMINT UNSIGNED DEFAULT NULL,
  PRIMARY KEY (host_id, parent_host_id),
  UNIQUE KEY unique_order (host_id, weight),
  CONSTRAINT icinga_host_inheritance_host
    FOREIGN KEY host (host_id)
    REFERENCES icinga_host (id)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT icinga_host_inheritance_parent_host
    FOREIGN KEY host (parent_host_id)
    REFERENCES icinga_host (id)
    ON DELETE RESTRICT
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE icinga_host_field (
  host_id INT(10) UNSIGNED NOT NULL COMMENT 'Makes only sense for templates',
  datafield_id INT(10) UNSIGNED NOT NULL,
  is_required ENUM('y', 'n') NOT NULL,
  var_filter TEXT DEFAULT NULL,
  PRIMARY KEY (host_id, datafield_id),
  CONSTRAINT icinga_host_field_host
  FOREIGN KEY host(host_id)
    REFERENCES icinga_host (id)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT icinga_host_field_datafield
  FOREIGN KEY datafield(datafield_id)
  REFERENCES director_datafield (id)
    ON DELETE CASCADE
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE icinga_host_var (
  host_id INT(10) UNSIGNED NOT NULL,
  varname VARCHAR(255) NOT NULL COLLATE utf8_bin,
  varvalue TEXT DEFAULT NULL,
  format enum ('string', 'json', 'expression'), -- immer string vorerst
  checksum VARBINARY(20) DEFAULT NULL,
  PRIMARY KEY (host_id, varname),
  INDEX search_idx (varname),
  INDEX checksum (checksum),
  CONSTRAINT icinga_host_var_host
    FOREIGN KEY host (host_id)
    REFERENCES icinga_host (id)
    ON DELETE CASCADE
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE icinga_service_set (
  id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  object_name VARCHAR(128) NOT NULL,
  object_type ENUM('object', 'template', 'external_object') NOT NULL,
  host_id INT(10) UNSIGNED DEFAULT NULL,
  description TEXT DEFAULT NULL,
  assign_filter TEXT DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY object_key (object_name, host_id),
  CONSTRAINT icinga_service_set_host
    FOREIGN KEY host (host_id)
    REFERENCES icinga_host (id)
    ON DELETE CASCADE
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE icinga_service_template_choice (
  id INT(10) UNSIGNED AUTO_INCREMENT NOT NULL,
  object_name VARCHAR(64) NOT NULL,
  description TEXT DEFAULT NULL,
  min_required SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  max_allowed SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  required_template_id INT(10) UNSIGNED DEFAULT NULL,
  allowed_roles VARCHAR(255) DEFAULT NULL,
  CONSTRAINT service_template_choice_required_template
  FOREIGN KEY required_template (required_template_id)
  REFERENCES icinga_service (id)
    ON DELETE RESTRICT
    ON UPDATE CASCADE,
  PRIMARY KEY (id),
  UNIQUE KEY (object_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE icinga_service (
  id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  object_name VARCHAR(255) NOT NULL,
  object_type ENUM('object', 'template', 'apply') NOT NULL,
  disabled ENUM('y', 'n') NOT NULL DEFAULT 'n',
  display_name VARCHAR(255) DEFAULT NULL,
  host_id INT(10) UNSIGNED DEFAULT NULL,
  service_set_id INT(10) UNSIGNED DEFAULT NULL,
  check_command_id INT(10) UNSIGNED DEFAULT NULL,
  max_check_attempts MEDIUMINT UNSIGNED DEFAULT NULL,
  check_period_id INT(10) UNSIGNED DEFAULT NULL,
  check_interval VARCHAR(8) DEFAULT NULL,
  retry_interval VARCHAR(8) DEFAULT NULL,
  check_timeout SMALLINT UNSIGNED DEFAULT NULL,
  enable_notifications ENUM('y', 'n') DEFAULT NULL,
  enable_active_checks ENUM('y', 'n') DEFAULT NULL,
  enable_passive_checks ENUM('y', 'n') DEFAULT NULL,
  enable_event_handler ENUM('y', 'n') DEFAULT NULL,
  enable_flapping ENUM('y', 'n') DEFAULT NULL,
  enable_perfdata ENUM('y', 'n') DEFAULT NULL,
  event_command_id INT(10) UNSIGNED DEFAULT NULL,
  flapping_threshold SMALLINT UNSIGNED DEFAULT NULL,
  volatile ENUM('y', 'n') DEFAULT NULL,
  zone_id INT(10) UNSIGNED DEFAULT NULL,
  command_endpoint_id INT(10) UNSIGNED DEFAULT NULL,
  notes TEXT DEFAULT NULL,
  notes_url VARCHAR(255) DEFAULT NULL,
  action_url VARCHAR(255) DEFAULT NULL,
  icon_image VARCHAR(255) DEFAULT NULL,
  icon_image_alt VARCHAR(255) DEFAULT NULL,
  use_agent ENUM('y', 'n') DEFAULT NULL,
  apply_for VARCHAR(255) DEFAULT NULL,
  use_var_overrides ENUM('y', 'n') DEFAULT NULL,
  assign_filter TEXT DEFAULT NULL,
  template_choice_id INT(10) UNSIGNED DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY object_key (object_name, host_id),
  CONSTRAINT icinga_service_host
    FOREIGN KEY host (host_id)
    REFERENCES icinga_host (id)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT icinga_service_zone
    FOREIGN KEY zone (zone_id)
    REFERENCES icinga_zone (id)
    ON DELETE RESTRICT
    ON UPDATE CASCADE,
  CONSTRAINT icinga_service_check_period
    FOREIGN KEY timeperiod (check_period_id)
    REFERENCES icinga_timeperiod (id)
    ON DELETE RESTRICT
    ON UPDATE CASCADE,
  CONSTRAINT icinga_service_check_command
    FOREIGN KEY check_command (check_command_id)
    REFERENCES icinga_command (id)
    ON DELETE RESTRICT
    ON UPDATE CASCADE,
  CONSTRAINT icinga_service_event_command
    FOREIGN KEY event_command (event_command_id)
    REFERENCES icinga_command (id)
    ON DELETE RESTRICT
    ON UPDATE CASCADE,
  CONSTRAINT icinga_service_command_endpoint
    FOREIGN KEY command_endpoint (command_endpoint_id)
    REFERENCES icinga_endpoint (id)
    ON DELETE RESTRICT
    ON UPDATE CASCADE,
  CONSTRAINT icinga_service_service_set
    FOREIGN KEY service_set (service_set_id)
    REFERENCES icinga_service_set (id)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT icinga_service_template_choice
    FOREIGN KEY choice (template_choice_id)
    REFERENCES icinga_service_template_choice (id)
    ON DELETE SET NULL
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE icinga_service_inheritance (
  service_id INT(10) UNSIGNED NOT NULL,
  parent_service_id INT(10) UNSIGNED NOT NULL,
  weight MEDIUMINT UNSIGNED DEFAULT NULL,
  PRIMARY KEY (service_id, parent_service_id),
  UNIQUE KEY unique_order (service_id, weight),
  CONSTRAINT icinga_service_inheritance_service
  FOREIGN KEY host (service_id)
  REFERENCES icinga_service (id)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT icinga_service_inheritance_parent_service
  FOREIGN KEY host (parent_service_id)
  REFERENCES icinga_service (id)
    ON DELETE RESTRICT
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE icinga_service_var (
  service_id INT(10) UNSIGNED NOT NULL,
  varname VARCHAR(255) NOT NULL COLLATE utf8_bin,
  varvalue TEXT DEFAULT NULL,
  format enum ('string', 'json', 'expression'),
  checksum VARBINARY(20) DEFAULT NULL,
  PRIMARY KEY (service_id, varname),
  INDEX search_idx (varname),
  INDEX checksum (checksum),
  CONSTRAINT icinga_service_var_service
    FOREIGN KEY service (service_id)
    REFERENCES icinga_service (id)
    ON DELETE CASCADE
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE icinga_service_field (
  service_id INT(10) UNSIGNED NOT NULL COMMENT 'Makes only sense for templates',
  datafield_id INT(10) UNSIGNED NOT NULL,
  is_required ENUM('y', 'n') NOT NULL,
  var_filter TEXT DEFAULT NULL,
  PRIMARY KEY (service_id, datafield_id),
  CONSTRAINT icinga_service_field_service
  FOREIGN KEY service(service_id)
  REFERENCES icinga_service (id)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT icinga_service_field_datafield
  FOREIGN KEY datafield(datafield_id)
  REFERENCES director_datafield (id)
    ON DELETE CASCADE
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE icinga_host_service (
  host_id INT(10) UNSIGNED NOT NULL,
  service_id INT(10) UNSIGNED NOT NULL,
  PRIMARY KEY (host_id, service_id),
  CONSTRAINT icinga_host_service_host
    FOREIGN KEY host (host_id)
    REFERENCES icinga_host (id)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT icinga_host_service_service
    FOREIGN KEY service (service_id)
    REFERENCES icinga_service (id)
    ON DELETE CASCADE
    ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE icinga_service_set_inheritance (
  service_set_id INT(10) UNSIGNED NOT NULL,
  parent_service_set_id INT(10) UNSIGNED NOT NULL,
  weight MEDIUMINT UNSIGNED DEFAULT NULL,
  PRIMARY KEY (service_set_id, parent_service_set_id),
  UNIQUE KEY unique_order (service_set_id, weight),
  CONSTRAINT icinga_service_set_inheritance_set
  FOREIGN KEY host (service_set_id)
  REFERENCES icinga_service_set (id)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT icinga_service_set_inheritance_parent
  FOREIGN KEY host (parent_service_set_id)
  REFERENCES icinga_service_set (id)
    ON DELETE RESTRICT
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE icinga_service_set_var (
  service_set_id INT(10) UNSIGNED NOT NULL,
  varname VARCHAR(255) NOT NULL COLLATE utf8_bin,
  varvalue TEXT DEFAULT NULL,
  format ENUM('string', 'expression', 'json') NOT NULL DEFAULT 'string',
  checksum VARBINARY(20) DEFAULT NULL,
  PRIMARY KEY (service_set_id, varname),
  INDEX search_idx (varname),
  INDEX checksum (checksum),
  CONSTRAINT icinga_service_set_var_service
    FOREIGN KEY command (service_set_id)
    REFERENCES icinga_service_set (id)
    ON DELETE CASCADE
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE icinga_hostgroup (
  id INT(10) UNSIGNED AUTO_INCREMENT NOT NULL,
  object_name VARCHAR(255) NOT NULL,
  object_type ENUM('object', 'template', 'external_object') NOT NULL,
  disabled ENUM('y', 'n') NOT NULL DEFAULT 'n',
  display_name VARCHAR(255) DEFAULT NULL,
  assign_filter TEXT DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE INDEX object_name (object_name),
  KEY search_idx (display_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- TODO: probably useless
CREATE TABLE icinga_hostgroup_inheritance (
  hostgroup_id INT(10) UNSIGNED NOT NULL,
  parent_hostgroup_id INT(10) UNSIGNED NOT NULL,
  weight MEDIUMINT UNSIGNED DEFAULT NULL,
  PRIMARY KEY (hostgroup_id, parent_hostgroup_id),
  UNIQUE KEY unique_order (hostgroup_id, weight),
  CONSTRAINT icinga_hostgroup_inheritance_hostgroup
  FOREIGN KEY host (hostgroup_id)
  REFERENCES icinga_hostgroup (id)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT icinga_hostgroup_inheritance_parent_hostgroup
  FOREIGN KEY host (parent_hostgroup_id)
  REFERENCES icinga_hostgroup (id)
    ON DELETE RESTRICT
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE icinga_servicegroup (
  id INT(10) UNSIGNED AUTO_INCREMENT NOT NULL,
  object_name VARCHAR(255) DEFAULT NULL,
  object_type ENUM('object', 'template') NOT NULL,
  disabled ENUM('y', 'n') NOT NULL DEFAULT 'n',
  display_name VARCHAR(255) DEFAULT NULL,
  assign_filter TEXT DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE INDEX object_name (object_name),
  KEY search_idx (display_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE icinga_servicegroup_inheritance (
  servicegroup_id INT(10) UNSIGNED NOT NULL,
  parent_servicegroup_id INT(10) UNSIGNED NOT NULL,
  weight MEDIUMINT UNSIGNED DEFAULT NULL,
  PRIMARY KEY (servicegroup_id, parent_servicegroup_id),
  UNIQUE KEY unique_order (servicegroup_id, weight),
  CONSTRAINT icinga_servicegroup_inheritance_servicegroup
  FOREIGN KEY host (servicegroup_id)
  REFERENCES icinga_servicegroup (id)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT icinga_servicegroup_inheritance_parent_servicegroup
  FOREIGN KEY host (parent_servicegroup_id)
  REFERENCES icinga_servicegroup (id)
    ON DELETE RESTRICT
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE icinga_servicegroup_service (
  servicegroup_id INT(10) UNSIGNED NOT NULL,
  service_id INT(10) UNSIGNED NOT NULL,
  PRIMARY KEY (servicegroup_id, service_id),
  CONSTRAINT icinga_servicegroup_service_service
    FOREIGN KEY service (service_id)
    REFERENCES icinga_service (id)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT icinga_servicegroup_service_servicegroup
    FOREIGN KEY servicegroup (servicegroup_id)
    REFERENCES icinga_servicegroup (id)
    ON DELETE CASCADE
    ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE icinga_hostgroup_host (
  hostgroup_id INT(10) UNSIGNED NOT NULL,
  host_id INT(10) UNSIGNED NOT NULL,
  PRIMARY KEY (hostgroup_id, host_id),
  CONSTRAINT icinga_hostgroup_host_host
    FOREIGN KEY host (host_id)
    REFERENCES icinga_host (id)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT icinga_hostgroup_host_hostgroup
    FOREIGN KEY hostgroup (hostgroup_id)
    REFERENCES icinga_hostgroup (id)
    ON DELETE CASCADE
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE icinga_hostgroup_host_resolved (
  hostgroup_id INT(10) UNSIGNED NOT NULL,
  host_id INT(10) UNSIGNED NOT NULL,
  PRIMARY KEY (hostgroup_id, host_id),
  CONSTRAINT icinga_hostgroup_host_resolved_host
  FOREIGN KEY host (host_id)
  REFERENCES icinga_host (id)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT icinga_hostgroup_host_resolved_hostgroup
  FOREIGN KEY hostgroup (hostgroup_id)
  REFERENCES icinga_hostgroup (id)
    ON DELETE CASCADE
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE icinga_hostgroup_parent (
  hostgroup_id INT(10) UNSIGNED NOT NULL,
  parent_hostgroup_id INT(10) UNSIGNED NOT NULL,
  PRIMARY KEY (hostgroup_id, parent_hostgroup_id),
  CONSTRAINT icinga_hostgroup_parent_hostgroup
    FOREIGN KEY hostgroup (hostgroup_id)
    REFERENCES icinga_hostgroup (id)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT icinga_hostgroup_parent_parent
    FOREIGN KEY parent (parent_hostgroup_id)
    REFERENCES icinga_hostgroup (id)
    ON DELETE RESTRICT
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE icinga_user (
  id INT(10) UNSIGNED AUTO_INCREMENT NOT NULL,
  object_name VARCHAR(255) DEFAULT NULL,
  object_type ENUM('object', 'template') NOT NULL,
  disabled ENUM('y', 'n') NOT NULL DEFAULT 'n',
  display_name VARCHAR(255) DEFAULT NULL,
  email VARCHAR(255) DEFAULT NULL,
  pager VARCHAR(255) DEFAULT NULL,
  enable_notifications ENUM('y', 'n') DEFAULT NULL,
  period_id INT(10) UNSIGNED DEFAULT NULL,
  zone_id INT(10) UNSIGNED DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE INDEX object_name (object_name, zone_id),
  CONSTRAINT icinga_user_zone
    FOREIGN KEY zone (zone_id)
    REFERENCES icinga_zone (id)
    ON DELETE RESTRICT
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE icinga_user_inheritance (
  user_id INT(10) UNSIGNED NOT NULL,
  parent_user_id INT(10) UNSIGNED NOT NULL,
  weight MEDIUMINT UNSIGNED DEFAULT NULL,
  PRIMARY KEY (user_id, parent_user_id),
  UNIQUE KEY unique_order (user_id, weight),
  CONSTRAINT icinga_user_inheritance_user
  FOREIGN KEY host (user_id)
  REFERENCES icinga_user (id)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT icinga_user_inheritance_parent_user
  FOREIGN KEY host (parent_user_id)
  REFERENCES icinga_user (id)
    ON DELETE RESTRICT
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE icinga_user_states_set (
  user_id INT(10) UNSIGNED NOT NULL,
  property ENUM(
    'OK',
    'Warning',
    'Critical',
    'Unknown',
    'Up',
    'Down'
  ) NOT NULL,
  merge_behaviour ENUM('override', 'extend', 'blacklist') NOT NULL DEFAULT 'override'
    COMMENT 'override: = [], extend: += [], blacklist: -= []',
  PRIMARY KEY (user_id, property, merge_behaviour),
  CONSTRAINT icinga_user_states_set_user
    FOREIGN KEY icinga_user (user_id)
    REFERENCES icinga_user (id)
    ON DELETE CASCADE
    ON UPDATE CASCADE
)  ENGINE=InnoDB;

CREATE TABLE icinga_user_types_set (
  user_id INT(10) UNSIGNED NOT NULL,
  property ENUM(
    'DowntimeStart',
    'DowntimeEnd',
    'DowntimeRemoved',
    'Custom',
    'Acknowledgement',
    'Problem',
    'Recovery',
    'FlappingStart',
    'FlappingEnd'
  ) NOT NULL,
  merge_behaviour ENUM('override', 'extend', 'blacklist') NOT NULL DEFAULT 'override'
    COMMENT 'override: = [], extend: += [], blacklist: -= []',
  PRIMARY KEY (user_id, property, merge_behaviour),
  CONSTRAINT icinga_user_types_set_user
    FOREIGN KEY icinga_user (user_id)
    REFERENCES icinga_user (id)
    ON DELETE CASCADE
    ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE icinga_user_var (
  user_id INT(10) UNSIGNED NOT NULL,
  varname VARCHAR(255) NOT NULL COLLATE utf8_bin,
  varvalue TEXT DEFAULT NULL,
  format ENUM('string', 'json', 'expression') NOT NULL DEFAULT 'string',
  checksum VARBINARY(20) DEFAULT NULL,
  PRIMARY KEY (user_id, varname),
  INDEX search_idx (varname),
  INDEX checksum (checksum),
  CONSTRAINT icinga_user_var_user
    FOREIGN KEY icinga_user (user_id)
    REFERENCES icinga_user (id)
    ON DELETE CASCADE
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE icinga_user_field (
  user_id INT(10) UNSIGNED NOT NULL COMMENT 'Makes only sense for templates',
  datafield_id INT(10) UNSIGNED NOT NULL,
  is_required ENUM('y', 'n') NOT NULL,
  var_filter TEXT DEFAULT NULL,
  PRIMARY KEY (user_id, datafield_id),
  CONSTRAINT icinga_user_field_user
  FOREIGN KEY user(user_id)
    REFERENCES icinga_user (id)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT icinga_user_field_datafield
  FOREIGN KEY datafield(datafield_id)
  REFERENCES director_datafield (id)
    ON DELETE CASCADE
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE icinga_usergroup (
  id INT(10) UNSIGNED AUTO_INCREMENT NOT NULL,
  object_name VARCHAR(255) NOT NULL,
  object_type ENUM('object', 'template') NOT NULL,
  disabled ENUM('y', 'n') NOT NULL DEFAULT 'n',
  display_name VARCHAR(255) DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE INDEX object_name (object_name),
  KEY search_idx (display_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE icinga_usergroup_inheritance (
  usergroup_id INT(10) UNSIGNED NOT NULL,
  parent_usergroup_id INT(10) UNSIGNED NOT NULL,
  weight MEDIUMINT UNSIGNED DEFAULT NULL,
  PRIMARY KEY (usergroup_id, parent_usergroup_id),
  UNIQUE KEY unique_order (usergroup_id, weight),
  CONSTRAINT icinga_usergroup_inheritance_usergroup
  FOREIGN KEY usergroup (usergroup_id)
  REFERENCES icinga_usergroup (id)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT icinga_usergroup_inheritance_parent_usergroup
  FOREIGN KEY usergroup (parent_usergroup_id)
  REFERENCES icinga_usergroup (id)
    ON DELETE RESTRICT
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE icinga_usergroup_user (
  usergroup_id INT(10) UNSIGNED NOT NULL,
  user_id INT(10) UNSIGNED NOT NULL,
  PRIMARY KEY (usergroup_id, user_id),
  CONSTRAINT icinga_usergroup_user_user
    FOREIGN KEY icinga_user (user_id)
    REFERENCES icinga_user (id)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT icinga_usergroup_user_usergroup
    FOREIGN KEY usergroup (usergroup_id)
    REFERENCES icinga_usergroup (id)
    ON DELETE CASCADE
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE icinga_usergroup_parent (
  usergroup_id INT(10) UNSIGNED NOT NULL,
  parent_usergroup_id INT(10) UNSIGNED NOT NULL,
  PRIMARY KEY (usergroup_id, parent_usergroup_id),
  CONSTRAINT icinga_usergroup_parent_usergroup
    FOREIGN KEY usergroup (usergroup_id)
    REFERENCES icinga_usergroup (id)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT icinga_usergroup_parent_parent
    FOREIGN KEY parent (parent_usergroup_id)
    REFERENCES icinga_usergroup (id)
    ON DELETE RESTRICT
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE icinga_notification (
  id INT(10) UNSIGNED AUTO_INCREMENT NOT NULL,
  object_name VARCHAR(255) DEFAULT NULL,
  object_type ENUM('object', 'template', 'apply') NOT NULL,
  disabled ENUM('y', 'n') NOT NULL DEFAULT 'n',
  apply_to ENUM('host', 'service') DEFAULT NULL,
  host_id INT(10) UNSIGNED DEFAULT NULL,
  service_id INT(10) UNSIGNED DEFAULT NULL,
  times_begin INT(10) UNSIGNED DEFAULT NULL,
  times_end INT(10) UNSIGNED DEFAULT NULL,
  notification_interval INT(10) UNSIGNED DEFAULT NULL,
  command_id INT(10) UNSIGNED DEFAULT NULL,
  period_id INT(10) UNSIGNED DEFAULT NULL,
  zone_id INT(10) UNSIGNED DEFAULT NULL,
  assign_filter TEXT DEFAULT NULL,
  PRIMARY KEY (id),
  CONSTRAINT icinga_notification_host
    FOREIGN KEY host (host_id)
    REFERENCES icinga_host (id)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT icinga_notification_service
    FOREIGN KEY service (service_id)
    REFERENCES icinga_service (id)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT icinga_notification_command
    FOREIGN KEY command (command_id)
    REFERENCES icinga_command (id)
    ON DELETE RESTRICT
    ON UPDATE CASCADE,
  CONSTRAINT icinga_notification_period
    FOREIGN KEY period (period_id)
    REFERENCES icinga_timeperiod (id)
    ON DELETE RESTRICT
    ON UPDATE CASCADE,
  CONSTRAINT icinga_notification_zone
    FOREIGN KEY zone (zone_id)
    REFERENCES icinga_zone (id)
    ON DELETE RESTRICT
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE icinga_notification_var (
  notification_id INT(10) UNSIGNED NOT NULL,
  varname VARCHAR(255) NOT NULL COLLATE utf8_bin,
  varvalue TEXT DEFAULT NULL,
  format enum ('string', 'json', 'expression'),
  checksum VARBINARY(20) DEFAULT NULL,
  PRIMARY KEY (notification_id, varname),
  INDEX search_idx (varname),
  INDEX checksum (checksum),
  CONSTRAINT icinga_notification_var_notification
    FOREIGN KEY notification (notification_id)
    REFERENCES icinga_notification (id)
    ON DELETE CASCADE
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE icinga_notification_field (
  notification_id INT(10) UNSIGNED NOT NULL COMMENT 'Makes only sense for templates',
  datafield_id INT(10) UNSIGNED NOT NULL,
  is_required ENUM('y', 'n') NOT NULL,
  var_filter TEXT DEFAULT NULL,
  PRIMARY KEY (notification_id, datafield_id),
  CONSTRAINT icinga_notification_field_notification
  FOREIGN KEY notification (notification_id)
    REFERENCES icinga_notification (id)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT icinga_notification_field_datafield
  FOREIGN KEY datafield(datafield_id)
  REFERENCES director_datafield (id)
    ON DELETE CASCADE
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE icinga_notification_inheritance (
  notification_id INT(10) UNSIGNED NOT NULL,
  parent_notification_id INT(10) UNSIGNED NOT NULL,
  weight MEDIUMINT UNSIGNED DEFAULT NULL,
  PRIMARY KEY (notification_id, parent_notification_id),
  UNIQUE KEY unique_order (notification_id, weight),
  CONSTRAINT icinga_notification_inheritance_notification
  FOREIGN KEY host (notification_id)
  REFERENCES icinga_notification (id)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT icinga_notification_inheritance_parent_notification
  FOREIGN KEY host (parent_notification_id)
  REFERENCES icinga_notification (id)
    ON DELETE RESTRICT
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE icinga_notification_states_set (
  notification_id INT(10) UNSIGNED NOT NULL,
  property ENUM(
    'OK',
    'Warning',
    'Critical',
    'Unknown',
    'Up',
    'Down'
  ) NOT NULL,
  merge_behaviour ENUM('override', 'extend', 'blacklist') NOT NULL DEFAULT 'override'
    COMMENT 'override: = [], extend: += [], blacklist: -= []',
  PRIMARY KEY (notification_id, property, merge_behaviour),
  CONSTRAINT icinga_notification_states_set_notification
    FOREIGN KEY icinga_notification (notification_id)
    REFERENCES icinga_notification (id)
    ON DELETE CASCADE
    ON UPDATE CASCADE
)  ENGINE=InnoDB;

CREATE TABLE icinga_notification_types_set (
  notification_id INT(10) UNSIGNED NOT NULL,
  property ENUM(
    'DowntimeStart',
    'DowntimeEnd',
    'DowntimeRemoved',
    'Custom',
    'Acknowledgement',
    'Problem',
    'Recovery',
    'FlappingStart',
    'FlappingEnd'
  ) NOT NULL,
  merge_behaviour ENUM('override', 'extend', 'blacklist') NOT NULL DEFAULT 'override'
    COMMENT 'override: = [], extend: += [], blacklist: -= []',
  PRIMARY KEY (notification_id, property, merge_behaviour),
  CONSTRAINT icinga_notification_types_set_notification
    FOREIGN KEY icinga_notification (notification_id)
    REFERENCES icinga_notification (id)
    ON DELETE CASCADE
    ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE icinga_notification_user (
  notification_id INT(10) UNSIGNED NOT NULL,
  user_id INT(10) UNSIGNED NOT NULL,
  PRIMARY KEY (notification_id, user_id),
  CONSTRAINT icinga_notification_user_user
    FOREIGN KEY user (user_id)
    REFERENCES icinga_user (id)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT icinga_notification_user_notification
    FOREIGN KEY notification (notification_id)
    REFERENCES icinga_notification (id)
    ON DELETE CASCADE
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE icinga_notification_usergroup (
  notification_id INT(10) UNSIGNED NOT NULL,
  usergroup_id INT(10) UNSIGNED NOT NULL,
  PRIMARY KEY (notification_id, usergroup_id),
  CONSTRAINT icinga_notification_usergroup_usergroup
    FOREIGN KEY usergroup (usergroup_id)
    REFERENCES icinga_usergroup (id)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT icinga_notification_usergroup_notification
    FOREIGN KEY notification (notification_id)
    REFERENCES icinga_notification (id)
    ON DELETE CASCADE
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE import_source (
  id INT(10) UNSIGNED AUTO_INCREMENT NOT NULL,
  source_name VARCHAR(64) NOT NULL,
  key_column VARCHAR(64) NOT NULL,
  provider_class VARCHAR(72) NOT NULL,
  import_state ENUM(
    'unknown',
    'in-sync',
    'pending-changes',
    'failing'
  ) NOT NULL DEFAULT 'unknown',
  last_error_message TEXT DEFAULT NULL,
  last_attempt DATETIME DEFAULT NULL,
  description TEXT DEFAULT NULL,
  PRIMARY KEY (id),
  INDEX search_idx (key_column)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE import_source_setting (
  source_id INT(10) UNSIGNED NOT NULL,
  setting_name VARCHAR(64) NOT NULL,
  setting_value TEXT NOT NULL,
  PRIMARY KEY (source_id, setting_name),
  CONSTRAINT import_source_settings_source
    FOREIGN KEY source (source_id)
    REFERENCES import_source (id)
    ON DELETE CASCADE
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE import_row_modifier (
  id INT(10) UNSIGNED AUTO_INCREMENT NOT NULL,
  source_id INT(10) UNSIGNED NOT NULL,
  property_name VARCHAR(255) NOT NULL,
  target_property VARCHAR(255) DEFAULT NULL,
  provider_class VARCHAR(72) NOT NULL,
  priority SMALLINT UNSIGNED NOT NULL,
  description TEXT DEFAULT NULL,
  PRIMARY KEY (id),
  KEY search_idx (property_name),
  UNIQUE INDEX idx_prio (source_id, priority),
  CONSTRAINT row_modifier_import_source
    FOREIGN KEY source (source_id)
    REFERENCES import_source (id)
    ON DELETE CASCADE
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE import_row_modifier_setting (
  row_modifier_id INT UNSIGNED NOT NULL,
  setting_name VARCHAR(64) NOT NULL,
  setting_value TEXT DEFAULT NULL,
  PRIMARY KEY (row_modifier_id, setting_name),
  CONSTRAINT row_modifier_settings
    FOREIGN KEY row_modifier (row_modifier_id)
    REFERENCES import_row_modifier (id)
    ON DELETE CASCADE
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE imported_rowset (
  checksum VARBINARY(20) NOT NULL,
  PRIMARY KEY (checksum)
) ENGINE=InnoDB;

CREATE TABLE import_run (
  id INT(10) UNSIGNED AUTO_INCREMENT NOT NULL,
  source_id INT(10) UNSIGNED NOT NULL,
  rowset_checksum VARBINARY(20) DEFAULT NULL,
  start_time DATETIME NOT NULL,
  end_time DATETIME DEFAULT NULL,
  succeeded ENUM('y', 'n') DEFAULT NULL,
  PRIMARY KEY (id),
  CONSTRAINT import_run_source
    FOREIGN KEY import_source (source_id)
    REFERENCES import_source (id)
    ON DELETE CASCADE
    ON UPDATE RESTRICT,
  CONSTRAINT import_run_rowset
    FOREIGN KEY rowset (rowset_checksum)
    REFERENCES imported_rowset (checksum)
    ON DELETE RESTRICT
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE imported_row (
  checksum VARBINARY(20) NOT NULL COMMENT 'sha1(object_name;property_checksum;...)',
  object_name VARCHAR(255) NOT NULL,
  PRIMARY KEY (checksum)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE imported_rowset_row (
  rowset_checksum VARBINARY(20) NOT NULL,
  row_checksum VARBINARY(20) NOT NULL,
  PRIMARY KEY (rowset_checksum, row_checksum),
  CONSTRAINT imported_rowset_row_rowset
    FOREIGN KEY rowset_row_rowset (rowset_checksum)
    REFERENCES imported_rowset (checksum)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT imported_rowset_row_row
    FOREIGN KEY rowset_row_rowset (row_checksum)
    REFERENCES imported_row (checksum)
    ON DELETE RESTRICT
    ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE imported_property (
  checksum VARBINARY(20) NOT NULL,
  property_name VARCHAR(64) NOT NULL,
  property_value MEDIUMTEXT NOT NULL,
  format enum ('string', 'expression', 'json'),
  PRIMARY KEY (checksum),
  KEY search_idx (property_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE imported_row_property (
  row_checksum VARBINARY(20) NOT NULL,
  property_checksum VARBINARY(20) NOT NULL,
  PRIMARY KEY (row_checksum, property_checksum),
  CONSTRAINT imported_row_property_row
    FOREIGN KEY row_checksum (row_checksum)
    REFERENCES imported_row (checksum)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT imported_row_property_property
    FOREIGN KEY property_checksum (property_checksum)
    REFERENCES imported_property (checksum)
    ON DELETE RESTRICT
    ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE sync_rule (
  id INT(10) UNSIGNED AUTO_INCREMENT NOT NULL,
  rule_name VARCHAR(255) NOT NULL,
  object_type enum(
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
  ) NOT NULL,
  update_policy ENUM('merge', 'override', 'ignore') NOT NULL,
  purge_existing ENUM('y', 'n') NOT NULL DEFAULT 'n',
  filter_expression TEXT DEFAULT NULL,
  sync_state ENUM(
    'unknown',
    'in-sync',
    'pending-changes',
    'failing'
  ) NOT NULL DEFAULT 'unknown',
  last_error_message TEXT DEFAULT NULL,
  last_attempt DATETIME DEFAULT NULL,
  description TEXT DEFAULT NULL,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE sync_property (
  id INT(10) UNSIGNED AUTO_INCREMENT NOT NULL,
  rule_id INT(10) UNSIGNED NOT NULL,
  source_id INT(10) UNSIGNED NOT NULL,
  source_expression VARCHAR(255) NOT NULL,
  destination_field VARCHAR(64),
  priority SMALLINT UNSIGNED NOT NULL,
  filter_expression TEXT DEFAULT NULL,
  merge_policy ENUM('override', 'merge') NOT NULL,
  PRIMARY KEY (id),
  CONSTRAINT sync_property_rule
    FOREIGN KEY sync_rule (rule_id)
    REFERENCES sync_rule (id)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT sync_property_source
    FOREIGN KEY import_source (source_id)
    REFERENCES import_source (id)
    ON DELETE RESTRICT
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE sync_run (
  id BIGINT(10) UNSIGNED AUTO_INCREMENT NOT NULL,
  rule_id INT(10) UNSIGNED DEFAULT NULL,
  rule_name VARCHAR(255) NOT NULL,
  start_time DATETIME NOT NULL,
  duration_ms INT(10) UNSIGNED DEFAULT NULL,
  objects_deleted INT(10) UNSIGNED DEFAULT 0,
  objects_created INT(10) UNSIGNED DEFAULT 0,
  objects_modified INT(10) UNSIGNED DEFAULT 0,
  last_former_activity VARBINARY(20) DEFAULT NULL,
  last_related_activity VARBINARY(20) DEFAULT NULL,
  PRIMARY KEY (id),
  CONSTRAINT sync_run_rule
    FOREIGN KEY sync_rule (rule_id)
    REFERENCES sync_rule (id)
    ON DELETE SET NULL
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE icinga_var (
  checksum VARBINARY(20) NOT NULL,
  rendered_checksum VARBINARY(20) NOT NULL,
  varname VARCHAR(255) NOT NULL COLLATE utf8_bin,
  varvalue TEXT NOT NULL,
  rendered TEXT NOT NULL,
  PRIMARY KEY (checksum),
  INDEX search_idx (varname)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE icinga_flat_var (
  var_checksum VARBINARY(20) NOT NULL,
  flatname_checksum VARBINARY(20) NOT NULL,
  flatname VARCHAR(512) NOT NULL COLLATE utf8_bin,
  flatvalue TEXT NOT NULL,
  PRIMARY KEY (var_checksum, flatname_checksum),
  INDEX search_varname (flatname (191)),
  INDEX search_varvalue (flatvalue (128)),
  CONSTRAINT flat_var_var
  FOREIGN KEY checksum (var_checksum)
  REFERENCES icinga_var (checksum)
    ON DELETE CASCADE
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE icinga_command_resolved_var (
  command_id INT(10) UNSIGNED NOT NULL,
  varname VARCHAR(255) NOT NULL COLLATE utf8_bin,
  checksum VARBINARY(20) NOT NULL,
  PRIMARY KEY (command_id, checksum),
  INDEX search_varname (varname),
  CONSTRAINT command_resolved_var_command
  FOREIGN KEY command (command_id)
  REFERENCES icinga_command (id)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT command_resolved_var_checksum
  FOREIGN KEY checksum (checksum)
  REFERENCES icinga_var (checksum)
    ON DELETE RESTRICT
    ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE icinga_host_resolved_var (
  host_id INT(10) UNSIGNED NOT NULL,
  varname VARCHAR(255) NOT NULL COLLATE utf8_bin,
  checksum VARBINARY(20) NOT NULL,
  PRIMARY KEY (host_id, checksum),
  INDEX search_varname (varname),
  FOREIGN KEY host_resolved_var_host (host_id)
  REFERENCES icinga_host (id)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  FOREIGN KEY host_resolved_var_checksum (checksum)
  REFERENCES icinga_var (checksum)
    ON DELETE RESTRICT
    ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE icinga_notification_resolved_var (
  notification_id INT(10) UNSIGNED NOT NULL,
  varname VARCHAR(255) NOT NULL COLLATE utf8_bin,
  checksum VARBINARY(20) NOT NULL,
  PRIMARY KEY (notification_id, checksum),
  INDEX search_varname (varname),
  FOREIGN KEY notification_resolved_var_notification (notification_id)
  REFERENCES icinga_notification (id)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  FOREIGN KEY notification_resolved_var_checksum (checksum)
  REFERENCES icinga_var (checksum)
    ON DELETE RESTRICT
    ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE icinga_service_set_resolved_var (
  service_set_id INT(10) UNSIGNED NOT NULL,
  varname VARCHAR(255) NOT NULL COLLATE utf8_bin,
  checksum VARBINARY(20) NOT NULL,
  PRIMARY KEY (service_set_id, checksum),
  INDEX search_varname (varname),
  FOREIGN KEY service_set_resolved_var_service_set (service_set_id)
  REFERENCES icinga_service_set (id)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  FOREIGN KEY service_set_resolved_var_checksum(checksum)
  REFERENCES icinga_var (checksum)
    ON DELETE RESTRICT
    ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE icinga_service_resolved_var (
  service_id INT(10) UNSIGNED NOT NULL,
  varname VARCHAR(255) NOT NULL COLLATE utf8_bin,
  checksum VARBINARY(20) NOT NULL,
  PRIMARY KEY (service_id, checksum),
  INDEX search_varname (varname),
  FOREIGN KEY service_resolve_var_service (service_id)
  REFERENCES icinga_service (id)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  FOREIGN KEY service_resolve_var_checksum(checksum)
  REFERENCES icinga_var (checksum)
    ON DELETE RESTRICT
    ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE icinga_user_resolved_var (
  user_id INT(10) UNSIGNED NOT NULL,
  varname VARCHAR(255) NOT NULL COLLATE utf8_bin,
  checksum VARBINARY(20) NOT NULL,
  PRIMARY KEY (user_id, checksum),
  INDEX search_varname (varname),
  FOREIGN KEY user_resolve_var_user (user_id)
  REFERENCES icinga_user (id)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  FOREIGN KEY user_resolve_var_checksum(checksum)
  REFERENCES icinga_var (checksum)
    ON DELETE RESTRICT
    ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE icinga_dependency (
  id INT(10) UNSIGNED AUTO_INCREMENT NOT NULL,
  object_name VARCHAR(255) DEFAULT NULL,
  object_type ENUM('object', 'template', 'apply') NOT NULL,
  disabled ENUM('y', 'n') NOT NULL DEFAULT 'n',
  apply_to ENUM('host', 'service') DEFAULT NULL,
  parent_host_id INT(10) UNSIGNED DEFAULT NULL,
  parent_service_id INT(10) UNSIGNED DEFAULT NULL,
  child_host_id INT(10) UNSIGNED DEFAULT NULL,
  child_service_id INT(10) UNSIGNED DEFAULT NULL,
  disable_checks ENUM('y', 'n') DEFAULT NULL,
  disable_notifications ENUM('y', 'n') DEFAULT NULL,
  ignore_soft_states ENUM('y', 'n') DEFAULT NULL,
  period_id INT(10) UNSIGNED DEFAULT NULL,
  zone_id INT(10) UNSIGNED DEFAULT NULL,
  assign_filter TEXT DEFAULT NULL,
  parent_service_by_name VARCHAR(255) DEFAULT NULL,
  PRIMARY KEY (id),
  CONSTRAINT icinga_dependency_parent_host
    FOREIGN KEY parent_host (parent_host_id)
    REFERENCES icinga_host (id)
    ON DELETE RESTRICT
    ON UPDATE CASCADE,
  CONSTRAINT icinga_dependency_parent_service
    FOREIGN KEY parent_service (parent_service_id)
    REFERENCES icinga_service (id)
    ON DELETE RESTRICT
    ON UPDATE CASCADE,
  CONSTRAINT icinga_dependency_child_host
    FOREIGN KEY child_host (child_host_id)
    REFERENCES icinga_host (id)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT icinga_dependency_child_service
    FOREIGN KEY child_service (child_service_id)
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE icinga_dependency_inheritance (
  dependency_id INT(10) UNSIGNED NOT NULL,
  parent_dependency_id INT(10) UNSIGNED NOT NULL,
  weight MEDIUMINT UNSIGNED DEFAULT NULL,
  PRIMARY KEY (dependency_id, parent_dependency_id),
  UNIQUE KEY unique_order (dependency_id, weight),
  CONSTRAINT icinga_dependency_inheritance_dependency
    FOREIGN KEY dependency (dependency_id)
    REFERENCES icinga_dependency (id)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT icinga_dependency_inheritance_parent_dependency
    FOREIGN KEY parent_dependency (parent_dependency_id)
    REFERENCES icinga_dependency (id)
    ON DELETE RESTRICT
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE icinga_dependency_states_set (
  dependency_id INT(10) UNSIGNED NOT NULL,
  property ENUM(
    'OK',
    'Warning',
    'Critical',
    'Unknown',
    'Up',
    'Down'
  ) NOT NULL,
  merge_behaviour ENUM('override', 'extend', 'blacklist') NOT NULL DEFAULT 'override'
    COMMENT 'override: = [], extend: += [], blacklist: -= []',
  PRIMARY KEY (dependency_id, property, merge_behaviour),
  CONSTRAINT icinga_dependency_states_set_dependency
    FOREIGN KEY icinga_dependency (dependency_id)
    REFERENCES icinga_dependency (id)
    ON DELETE CASCADE
    ON UPDATE CASCADE
) ENGINE=InnoDB;

INSERT INTO director_schema_migration
  (schema_version, migration_time)
  VALUES (144, NOW());
