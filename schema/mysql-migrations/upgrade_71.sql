CREATE TABLE icinga_notification (
  id INT(10) UNSIGNED AUTO_INCREMENT NOT NULL,
  object_name VARCHAR(255) DEFAULT NULL,
  object_type ENUM('object', 'template') NOT NULL,
  disabled ENUM('y', 'n') NOT NULL DEFAULT 'n',
  host_id INT(10) UNSIGNED DEFAULT NULL,
  service_id INT(10) UNSIGNED DEFAULT NULL,
  times_begin INT(10) UNSIGNED DEFAULT NULL,
  times_end INT(10) UNSIGNED DEFAULT NULL,
  notification_interval INT(10) UNSIGNED DEFAULT NULL,
  command_id INT(10) UNSIGNED DEFAULT NULL,
  period_id INT(10) UNSIGNED DEFAULT NULL,
  zone_id INT(10) UNSIGNED DEFAULT NULL,
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

INSERT INTO director_schema_migration
  SET migration_time = NOW(),
      schema_version = 71;
