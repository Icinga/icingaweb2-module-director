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

INSERT INTO director_schema_migration
  (schema_version, migration_time)
  VALUES (86, NOW());
