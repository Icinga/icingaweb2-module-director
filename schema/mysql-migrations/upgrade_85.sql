CREATE TABLE icinga_notification_assignment (
  id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  notification_id INT(10) UNSIGNED NOT NULL,
  filter_string TEXT NOT NULL,  
  PRIMARY KEY (id),
  CONSTRAINT icinga_notification_assignment
    FOREIGN KEY notification (notification_id)
    REFERENCES icinga_notification (id)
    ON DELETE CASCADE
    ON UPDATE CASCADE
) ENGINE=InnoDB;

INSERT INTO director_schema_migration
  (schema_version, migration_time)
  VALUES (85, NOW());
