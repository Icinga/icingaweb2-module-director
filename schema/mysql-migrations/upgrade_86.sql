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

INSERT INTO director_schema_migration
  (schema_version, migration_time)
  VALUES (86, NOW());
