CREATE TABLE icinga_notification_field (
  notification_id INT(10) UNSIGNED NOT NULL COMMENT 'Makes only sense for templates',
  datafield_id INT(10) UNSIGNED NOT NULL,
  is_required ENUM('y', 'n') NOT NULL,
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

INSERT INTO director_schema_migration
  (schema_version, migration_time)
  VALUES (117, NOW());
