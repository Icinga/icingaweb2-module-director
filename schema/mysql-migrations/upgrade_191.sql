CREATE TABLE icinga_usergroup_user_resolved
(
  usergroup_id INT(10) UNSIGNED NOT NULL,
  user_id      INT(10) UNSIGNED NOT NULL,
  PRIMARY KEY (usergroup_id, user_id),
  CONSTRAINT icinga_usergroup_user_resolved_user
    FOREIGN KEY user (user_id)
      REFERENCES icinga_user (id)
      ON DELETE CASCADE
      ON UPDATE CASCADE,
  CONSTRAINT icinga_usergroup_user_resolved_usergroup
    FOREIGN KEY usergroup (usergroup_id)
      REFERENCES icinga_usergroup (id)
      ON DELETE CASCADE
      ON UPDATE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8;

ALTER TABLE icinga_usergroup ADD COLUMN assign_filter TEXT DEFAULT NULL;

INSERT INTO director_schema_migration
(schema_version, migration_time)
VALUES (191, NOW());
