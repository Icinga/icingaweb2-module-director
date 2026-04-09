--
-- MySQL schema
-- ============
--
-- You should normally not be required to care about schema handling.
-- Director does all the migrations for you and guides you either in
-- the frontend or provides everything you need for automated migration
-- handling. Please find more related information in our documentation.

SET sql_mode = 'STRICT_ALL_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION,PIPES_AS_CONCAT,ANSI_QUOTES,ERROR_FOR_DIVISION_BY_ZERO';

CREATE TABLE director_daemon_info (
  instance_uuid_hex VARCHAR(32) NOT NULL, -- random by daemon
  schema_version SMALLINT UNSIGNED NOT NULL,
  fqdn VARCHAR(255) NOT NULL,
  username VARCHAR(64) NOT NULL,
  pid INT UNSIGNED NOT NULL,
  binary_path VARCHAR(128) NOT NULL,
  binary_realpath VARCHAR(128) NOT NULL,
  php_binary_path VARCHAR(128) NOT NULL,
  php_binary_realpath VARCHAR(128) NOT NULL,
  php_version VARCHAR(64) NOT NULL,
  php_integer_size SMALLINT NOT NULL,
  running_with_systemd ENUM('y', 'n') NOT NULL,
  ts_started BIGINT(20) NOT NULL,
  ts_stopped BIGINT(20) DEFAULT NULL,
  ts_last_modification BIGINT(20) DEFAULT NULL,
  ts_last_update BIGINT(20) DEFAULT NULL,
  process_info MEDIUMTEXT NOT NULL,
  PRIMARY KEY (instance_uuid_hex)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_bin;

CREATE TABLE director_activity_log (
  id BIGINT(20) UNSIGNED AUTO_INCREMENT NOT NULL,
  object_type VARCHAR(64) NOT NULL,
  object_name VARCHAR(255) NOT NULL,
  action_name ENUM('create', 'delete', 'modify') NOT NULL,
  old_properties MEDIUMTEXT DEFAULT NULL COMMENT 'Property hash, JSON',
  new_properties MEDIUMTEXT DEFAULT NULL COMMENT 'Property hash, JSON',
  author VARCHAR(64) NOT NULL,
  change_time TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  checksum VARBINARY(20) NOT NULL,
  parent_checksum VARBINARY(20) DEFAULT NULL,
  PRIMARY KEY (id),
  INDEX sort_idx (change_time),
  INDEX search_idx (object_name),
  INDEX search_idx2 (object_type(32), object_name(64), change_time),
  INDEX search_author (author),
  UNIQUE INDEX checksum (checksum)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE director_activity_log_remark (
  first_related_activity BIGINT(20) UNSIGNED NOT NULL,
  last_related_activity BIGINT(20) UNSIGNED NOT NULL,
  remark TEXT NOT NULL,
  PRIMARY KEY (first_related_activity, last_related_activity),
  CONSTRAINT activity_log_remark_begin
    FOREIGN KEY first_related_activity (first_related_activity)
      REFERENCES director_activity_log (id)
      ON DELETE CASCADE
      ON UPDATE CASCADE,
  CONSTRAINT activity_log_remark_end
    FOREIGN KEY last_related_activity (last_related_activity)
      REFERENCES director_activity_log (id)
      ON DELETE CASCADE
      ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE director_basket (
  uuid VARBINARY(16) NOT NULL,
  basket_name VARCHAR(64) NOT NULL,
  owner_type ENUM(
    'user',
    'usergroup',
    'role'
  ) NOT NULL,
  owner_value VARCHAR(255) NOT NULL,
  objects MEDIUMTEXT NOT NULL, -- json-encoded
  PRIMARY KEY (uuid),
  UNIQUE INDEX basket_name (basket_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_bin;

CREATE TABLE director_basket_content (
  checksum VARBINARY(20) NOT NULL,
  summary VARCHAR(500) NOT NULL, -- json
  content MEDIUMTEXT NOT NULL, -- json
  PRIMARY KEY (checksum)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_bin;

CREATE TABLE director_basket_snapshot (
  basket_uuid VARBINARY(16) NOT NULL,
  ts_create BIGINT(20) NOT NULL,
  content_checksum VARBINARY(20) NOT NULL,
  PRIMARY KEY (basket_uuid, ts_create),
  INDEX sort_idx (ts_create),
  CONSTRAINT basked_snapshot_basket
  FOREIGN KEY director_basket_snapshot (basket_uuid)
  REFERENCES director_basket (uuid)
    ON DELETE CASCADE
    ON UPDATE RESTRICT,
  CONSTRAINT basked_snapshot_content
  FOREIGN KEY content_checksum (content_checksum)
  REFERENCES director_basket_content (checksum)
    ON DELETE RESTRICT
    ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_bin;

CREATE TABLE director_generated_config (
  checksum VARBINARY(20) NOT NULL COMMENT 'SHA1(last_activity_checksum;file_path=checksum;file_path=checksum;...)',
  director_version VARCHAR(64) DEFAULT NULL,
  director_db_version INT(10) DEFAULT NULL,
  duration INT(10) UNSIGNED DEFAULT NULL COMMENT 'Config generation duration (ms)',
  first_activity_checksum VARBINARY(20) NOT NULL,
  last_activity_checksum VARBINARY(20) NOT NULL,
  PRIMARY KEY (checksum),
  CONSTRAINT director_generated_config_activity
    FOREIGN KEY (last_activity_checksum)
    REFERENCES director_activity_log (checksum)
    ON DELETE RESTRICT
    ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE director_generated_file (
  checksum VARBINARY(20) NOT NULL COMMENT 'SHA1(content)',
  content LONGTEXT NOT NULL,
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
  start_time TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  end_time TIMESTAMP NULL DEFAULT NULL,
  abort_time TIMESTAMP NULL DEFAULT NULL,
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
  INDEX (start_time),
  CONSTRAINT config_checksum
    FOREIGN KEY config_checksum (config_checksum)
    REFERENCES director_generated_config (checksum)
    ON DELETE SET NULL
    ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE director_datalist (
  id INT(10) UNSIGNED AUTO_INCREMENT NOT NULL,
  uuid VARBINARY(16) NOT NULL,
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

CREATE TABLE director_datafield_category (
  id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  category_name VARCHAR(255) NOT NULL,
  description TEXT DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY category_name (category_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE director_datafield (
  id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  uuid VARBINARY(16) NOT NULL,
  category_id INT(10) UNSIGNED DEFAULT NULL,
  varname VARCHAR(64) NOT NULL COLLATE utf8_bin,
  caption VARCHAR(255) NOT NULL,
  description TEXT DEFAULT NULL,
  datatype varchar(255) NOT NULL,
-- datatype_param? multiple ones?
  format enum ('string', 'json', 'expression'),
  PRIMARY KEY (id),
  KEY search_idx (varname),
  CONSTRAINT director_datafield_category
    FOREIGN KEY category (category_id)
    REFERENCES director_datafield_category (id)
    ON DELETE RESTRICT
    ON UPDATE CASCADE
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
  migration_time TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY(schema_version)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE director_setting (
  setting_name VARCHAR(64) NOT NULL,
  setting_value VARCHAR(255) NOT NULL,
  PRIMARY KEY(setting_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE icinga_zone (
  id INT(10) UNSIGNED AUTO_INCREMENT NOT NULL,
  uuid VARBINARY(16) NOT NULL,
  parent_id INT(10) UNSIGNED DEFAULT NULL,
  object_name VARCHAR(255) NOT NULL,
  object_type ENUM('object', 'template', 'external_object') NOT NULL,
  disabled ENUM('y', 'n') NOT NULL DEFAULT 'n',
  is_global ENUM('y', 'n') NOT NULL DEFAULT 'n',
  PRIMARY KEY (id),
  UNIQUE INDEX uuid (uuid),
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
  uuid VARBINARY(16) NOT NULL,
  object_name VARCHAR(255) NOT NULL,
  display_name VARCHAR(255) DEFAULT NULL,
  update_method VARCHAR(64) DEFAULT NULL COMMENT 'Usually LegacyTimePeriod',
  zone_id INT(10) UNSIGNED DEFAULT NULL,
  object_type ENUM('object', 'template') NOT NULL,
  disabled ENUM('y', 'n') NOT NULL DEFAULT 'n',
  prefer_includes ENUM('y', 'n') DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE INDEX uuid (uuid),
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
  ts_last_attempt BIGINT(20) NULL DEFAULT NULL,
  ts_last_error BIGINT(20) NULL DEFAULT NULL,
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
  uuid VARBINARY(16) NOT NULL,
  object_name VARCHAR(255) NOT NULL,
  object_type ENUM('object', 'template', 'external_object') NOT NULL
    COMMENT 'external_object is an attempt to work with existing commands',
  disabled ENUM('y', 'n') NOT NULL DEFAULT 'n',
  methods_execute VARCHAR(64) DEFAULT NULL,
  command TEXT DEFAULT NULL,
  is_string ENUM('y', 'n') NULL,
  -- env text DEFAULT NULL,
  -- vars text DEFAULT NULL,
  timeout SMALLINT UNSIGNED DEFAULT NULL,
  zone_id INT(10) UNSIGNED DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE INDEX uuid (uuid),
  UNIQUE INDEX object_name (object_name),
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
  uuid VARBINARY(16) NOT NULL,
  object_name VARCHAR(255) NOT NULL,
  object_type ENUM('object', 'template', 'external_object') NOT NULL,
  disabled ENUM('y', 'n') NOT NULL DEFAULT 'n',
  password VARCHAR(255) DEFAULT NULL,
  client_dn VARCHAR(64) DEFAULT NULL,
  permissions TEXT DEFAULT NULL COMMENT 'JSON-encoded permissions',
  PRIMARY KEY (id),
  UNIQUE INDEX uuid (uuid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE icinga_endpoint (
  id INT(10) UNSIGNED AUTO_INCREMENT NOT NULL,
  uuid VARBINARY(16) NOT NULL,
  zone_id INT(10) UNSIGNED DEFAULT NULL,
  object_name VARCHAR(255) NOT NULL,
  object_type ENUM('object', 'template', 'external_object') NOT NULL,
  disabled ENUM('y', 'n') NOT NULL DEFAULT 'n',
  host VARCHAR(255) DEFAULT NULL COMMENT 'IP address / hostname of remote node',
  port SMALLINT UNSIGNED DEFAULT NULL COMMENT '5665 if not set',
  log_duration VARCHAR(32) DEFAULT NULL COMMENT '1d if not set',
  apiuser_id INT(10) UNSIGNED DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE INDEX uuid (uuid),
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
  PRIMARY KEY (id),
  UNIQUE KEY (object_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE icinga_host (
  id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  uuid VARBINARY(16) NOT NULL,
  object_name VARCHAR(255) NOT NULL,
  object_type ENUM('object', 'template') NOT NULL,
  disabled ENUM('y', 'n') NOT NULL DEFAULT 'n',
  display_name VARCHAR(255) DEFAULT NULL,
  address VARCHAR(255) DEFAULT NULL,
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
  flapping_threshold_high SMALLINT UNSIGNED default null,
  flapping_threshold_low SMALLINT UNSIGNED default null,
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
  custom_endpoint_name VARCHAR(255) DEFAULT NULL,
  api_key VARCHAR(40) DEFAULT NULL,
  template_choice_id INT(10) UNSIGNED DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE INDEX uuid (uuid),
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
  varvalue MEDIUMTEXT DEFAULT NULL,
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

ALTER TABLE icinga_host_template_choice
  ADD CONSTRAINT host_template_choice_required_template
    FOREIGN KEY required_template (required_template_id)
    REFERENCES icinga_host (id)
    ON DELETE RESTRICT
    ON UPDATE CASCADE;

CREATE TABLE icinga_service_set (
  id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  uuid VARBINARY(16) NOT NULL,
  object_name VARCHAR(128) NOT NULL,
  object_type ENUM('object', 'template', 'external_object') NOT NULL,
  host_id INT(10) UNSIGNED DEFAULT NULL,
  description TEXT DEFAULT NULL,
  assign_filter TEXT DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE INDEX uuid (uuid),
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
  PRIMARY KEY (id),
  UNIQUE KEY (object_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE icinga_service (
  id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  uuid VARBINARY(16) NOT NULL,
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
  flapping_threshold_high SMALLINT UNSIGNED DEFAULT NULL,
  flapping_threshold_low SMALLINT UNSIGNED DEFAULT NULL,
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
  UNIQUE INDEX uuid (uuid),
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

ALTER TABLE icinga_service_template_choice
  ADD CONSTRAINT service_template_choice_required_template
    FOREIGN KEY required_template (required_template_id)
    REFERENCES icinga_service (id)
    ON DELETE RESTRICT
    ON UPDATE CASCADE;

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

CREATE TABLE icinga_host_service_blacklist (
  host_id INT(10) UNSIGNED NOT NULL,
  service_id INT(10) UNSIGNED NOT NULL,
  PRIMARY KEY (host_id, service_id),
  CONSTRAINT icinga_host_service_bl_host
  FOREIGN KEY host (host_id)
  REFERENCES icinga_host (id)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT icinga_host_service_bl_service
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
  uuid VARBINARY(16) NOT NULL,
  object_name VARCHAR(255) NOT NULL,
  object_type ENUM('object', 'template', 'external_object') NOT NULL,
  disabled ENUM('y', 'n') NOT NULL DEFAULT 'n',
  display_name VARCHAR(255) DEFAULT NULL,
  assign_filter TEXT DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE INDEX uuid (uuid),
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
  uuid VARBINARY(16) NOT NULL,
  object_name VARCHAR(255) DEFAULT NULL,
  object_type ENUM('object', 'template') NOT NULL,
  disabled ENUM('y', 'n') NOT NULL DEFAULT 'n',
  display_name VARCHAR(255) DEFAULT NULL,
  assign_filter TEXT DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE INDEX uuid (uuid),
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

CREATE TABLE icinga_servicegroup_service_resolved (
  servicegroup_id INT(10) UNSIGNED NOT NULL,
  service_id INT(10) UNSIGNED NOT NULL,
  PRIMARY KEY (servicegroup_id, service_id),
  CONSTRAINT icinga_servicegroup_service_resolved_service
  FOREIGN KEY service (service_id)
  REFERENCES icinga_service (id)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT icinga_servicegroup_service_resolved_servicegroup
  FOREIGN KEY servicegroup (servicegroup_id)
  REFERENCES icinga_servicegroup (id)
    ON DELETE CASCADE
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

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
  uuid VARBINARY(16) NOT NULL,
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
  UNIQUE INDEX uuid (uuid),
  UNIQUE INDEX object_name (object_name, zone_id),
  CONSTRAINT icinga_user_zone
    FOREIGN KEY zone (zone_id)
    REFERENCES icinga_zone (id)
    ON DELETE RESTRICT
    ON UPDATE CASCADE,
  CONSTRAINT icinga_user_period
    FOREIGN KEY period (period_id)
    REFERENCES icinga_timeperiod (id)
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
  uuid VARBINARY(16) NOT NULL,
  object_name VARCHAR(255) NOT NULL,
  object_type ENUM('object', 'template') NOT NULL,
  disabled ENUM('y', 'n') NOT NULL DEFAULT 'n',
  display_name VARCHAR(255) DEFAULT NULL,
  zone_id INT(10) UNSIGNED DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE INDEX uuid (uuid),
  UNIQUE INDEX object_name (object_name),
  KEY search_idx (display_name),
  CONSTRAINT icinga_usergroup_zone
    FOREIGN KEY zone (zone_id)
    REFERENCES icinga_zone (id)
      ON DELETE RESTRICT
      ON UPDATE CASCADE
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
  uuid VARBINARY(16) NOT NULL,
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
  users_var VARCHAR(255) DEFAULT NULL,
  user_groups_var VARCHAR(255) DEFAULT NULL,
  assign_filter TEXT DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE INDEX uuid (uuid),
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
  provider_class VARCHAR(128) NOT NULL,
  import_state ENUM(
    'unknown',
    'in-sync',
    'pending-changes',
    'failing'
  ) NOT NULL DEFAULT 'unknown',
  last_error_message TEXT DEFAULT NULL,
  last_attempt TIMESTAMP NULL DEFAULT NULL,
  description TEXT DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE INDEX source_name (source_name),
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
  provider_class VARCHAR(128) NOT NULL,
  priority SMALLINT UNSIGNED NOT NULL,
  filter_expression TEXT DEFAULT NULL,
  description TEXT DEFAULT NULL,
  PRIMARY KEY (id),
  KEY search_idx (property_name),
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
  start_time TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  end_time TIMESTAMP NULL DEFAULT NULL,
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
    'serviceSet',
    'scheduledDowntime',
    'notification',
    'dependency'
  ) NOT NULL,
  update_policy ENUM('merge', 'override', 'ignore', 'update-only') NOT NULL,
  purge_existing ENUM('y', 'n') NOT NULL DEFAULT 'n',
  purge_action ENUM('delete', 'disable') NULL DEFAULT NULL,
  filter_expression TEXT DEFAULT NULL,
  sync_state ENUM(
    'unknown',
    'in-sync',
    'pending-changes',
    'failing'
  ) NOT NULL DEFAULT 'unknown',
  last_error_message TEXT DEFAULT NULL,
  last_attempt TIMESTAMP NULL DEFAULT NULL,
  description TEXT DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE INDEX rule_name (rule_name)
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
  start_time TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
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
  uuid VARBINARY(16) NOT NULL,
  object_name VARCHAR(255) DEFAULT NULL,
  object_type ENUM('object', 'template', 'apply') NOT NULL,
  disabled ENUM('y', 'n') NOT NULL DEFAULT 'n',
  apply_to ENUM('host', 'service') DEFAULT NULL,
  parent_host_id INT(10) UNSIGNED DEFAULT NULL,
  parent_host_var VARCHAR(128) DEFAULT NULL,
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
  redundancy_group VARCHAR(255) DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE INDEX uuid (uuid),
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

CREATE TABLE icinga_timeperiod_include (
  timeperiod_id INT(10) UNSIGNED NOT NULL,
  include_id INT(10) UNSIGNED NOT NULL,
  PRIMARY KEY (timeperiod_id, include_id),
  CONSTRAINT icinga_timeperiod_include
  FOREIGN KEY timeperiod (include_id)
  REFERENCES icinga_timeperiod (id)
    ON DELETE RESTRICT
    ON UPDATE RESTRICT,
  CONSTRAINT icinga_timeperiod_include_timeperiod
  FOREIGN KEY include (timeperiod_id)
  REFERENCES icinga_timeperiod (id)
    ON DELETE CASCADE
    ON UPDATE CASCADE
);

CREATE TABLE icinga_timeperiod_exclude (
  timeperiod_id INT(10) UNSIGNED NOT NULL,
  exclude_id INT(10) UNSIGNED NOT NULL,
  PRIMARY KEY (timeperiod_id, exclude_id),
  CONSTRAINT icinga_timeperiod_exclude
  FOREIGN KEY timeperiod (exclude_id)
  REFERENCES icinga_timeperiod (id)
    ON DELETE RESTRICT
    ON UPDATE RESTRICT,
  CONSTRAINT icinga_timeperiod_exclude_timeperiod
  FOREIGN KEY exclude (timeperiod_id)
  REFERENCES icinga_timeperiod (id)
    ON DELETE CASCADE
    ON UPDATE CASCADE
);

CREATE TABLE icinga_scheduled_downtime (
  id INT(10) UNSIGNED AUTO_INCREMENT NOT NULL,
  uuid VARBINARY(16) NOT NULL,
  object_name VARCHAR(255) NOT NULL,
  zone_id INT(10) UNSIGNED DEFAULT NULL,
  object_type ENUM('object', 'template', 'apply') NOT NULL,
  disabled ENUM('y', 'n') NOT NULL DEFAULT 'n',
  apply_to ENUM('host', 'service') DEFAULT NULL,
  assign_filter TEXT DEFAULT NULL,
  author VARCHAR(255) DEFAULT NULL,
  comment TEXT DEFAULT NULL,
  fixed ENUM('y', 'n') DEFAULT NULL,
  duration INT(10) UNSIGNED DEFAULT NULL,
  with_services ENUM('y', 'n') NULL DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE INDEX uuid (uuid),
  UNIQUE INDEX object_name (object_name),
  CONSTRAINT icinga_scheduled_downtime_zone
  FOREIGN KEY zone (zone_id)
  REFERENCES icinga_zone (id)
    ON DELETE RESTRICT
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE icinga_scheduled_downtime_inheritance (
  scheduled_downtime_id INT(10) UNSIGNED NOT NULL,
  parent_scheduled_downtime_id INT(10) UNSIGNED NOT NULL,
  weight MEDIUMINT UNSIGNED DEFAULT NULL,
  PRIMARY KEY (scheduled_downtime_id, parent_scheduled_downtime_id),
  UNIQUE KEY unique_order (scheduled_downtime_id, weight),
  CONSTRAINT icinga_scheduled_downtime_inheritance_downtime
  FOREIGN KEY host (scheduled_downtime_id)
  REFERENCES icinga_scheduled_downtime (id)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT icinga_scheduled_downtime_inheritance_parent_downtime
  FOREIGN KEY host (parent_scheduled_downtime_id)
  REFERENCES icinga_scheduled_downtime (id)
    ON DELETE RESTRICT
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE icinga_scheduled_downtime_range (
  scheduled_downtime_id INT(10) UNSIGNED AUTO_INCREMENT NOT NULL,
  range_key VARCHAR(255) NOT NULL COMMENT 'monday, ...',
  range_value VARCHAR(255) NOT NULL COMMENT '00:00-24:00, ...',
  range_type ENUM('include', 'exclude') NOT NULL DEFAULT 'include'
  COMMENT 'include -> ranges {}, exclude ranges_ignore {} - not yet',
  merge_behaviour ENUM('set', 'add', 'substract') NOT NULL DEFAULT 'set'
  COMMENT 'set -> = {}, add -> += {}, substract -> -= {}',
  PRIMARY KEY (scheduled_downtime_id, range_type, range_key),
  CONSTRAINT icinga_scheduled_downtime_range_downtime
  FOREIGN KEY scheduled_downtime (scheduled_downtime_id)
  REFERENCES icinga_scheduled_downtime (id)
    ON DELETE CASCADE
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

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
  custom_endpoint_name VARCHAR(255) DEFAULT NULL,
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

CREATE TABLE branched_icinga_service_set (
  uuid VARBINARY(16) NOT NULL,
  branch_uuid VARBINARY(16) NOT NULL,
  branch_created ENUM('y', 'n') NOT NULL DEFAULT 'n',
  branch_deleted ENUM('y', 'n') NOT NULL DEFAULT 'n',

  object_name VARCHAR(128) DEFAULT NULL,
  object_type ENUM('object', 'template', 'external_object') DEFAULT NULL,
  host VARCHAR(255) DEFAULT NULL,
  description TEXT DEFAULT NULL,
  assign_filter TEXT DEFAULT NULL,


  imports TEXT DEFAULT NULL,
  set_null TEXT DEFAULT NULL,
  PRIMARY KEY (branch_uuid, uuid),
  INDEX search_object_name (object_name),
  CONSTRAINT icinga_service_set_branch
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
  users_var VARCHAR(255) DEFAULT NULL,
  user_groups_var VARCHAR(255) DEFAULT NULL,
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
  redundancy_group VARCHAR(255) DEFAULT NULL,

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
  VALUES (191, NOW());
