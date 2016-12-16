ALTER TABLE icinga_command_var
  ADD COLUMN checksum VARBINARY(20) DEFAULT NULL AFTER command_id,
  ADD INDEX search_idx (varname),
  ADD INDEX checksum (checksum);

ALTER TABLE icinga_host_var
  ADD COLUMN checksum VARBINARY(20) DEFAULT NULL AFTER host_id,
  ADD INDEX checksum (checksum);

ALTER TABLE icinga_notification_var
  ADD COLUMN checksum VARBINARY(20) DEFAULT NULL AFTER notification_id,
  ADD INDEX checksum (checksum);

ALTER TABLE icinga_service_set_var
  ADD COLUMN checksum VARBINARY(20) DEFAULT NULL AFTER service_set_id,
  ADD INDEX search_idx (varname),
  ADD INDEX checksum (checksum);

ALTER TABLE icinga_service_var
  ADD COLUMN checksum VARBINARY(20) DEFAULT NULL AFTER service_id,
  ADD INDEX checksum (checksum);

ALTER TABLE icinga_user_var
  ADD COLUMN checksum VARBINARY(20) DEFAULT NULL AFTER user_id,
  ADD INDEX checksum (checksum);

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

INSERT INTO director_schema_migration
  (schema_version, migration_time)
  VALUES (127, NOW());
