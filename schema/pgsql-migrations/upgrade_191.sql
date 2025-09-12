CREATE TABLE icinga_usergroup_user_resolved
(
  usergroup_id integer NOT NULL,
  user_id      integer NOT NULL,
  PRIMARY KEY (usergroup_id, user_id),
  CONSTRAINT icinga_usergroup_user_resolved_user
    FOREIGN KEY (user_id)
      REFERENCES icinga_user (id)
      ON DELETE CASCADE
      ON UPDATE CASCADE,
  CONSTRAINT icinga_usergroup_user_resolved_usergroup
    FOREIGN KEY (usergroup_id)
      REFERENCES icinga_usergroup (id)
      ON DELETE CASCADE
      ON UPDATE CASCADE
);

CREATE INDEX usergroup_user_resolved_user ON icinga_usergroup_user_resolved (user_id);
CREATE INDEX usergroup_user_resolved_usergroup ON icinga_usergroup_user_resolved (usergroup_id);

ALTER TABLE icinga_usergroup ADD COLUMN assign_filter text DEFAULT NULL;

INSERT INTO director_schema_migration
  (schema_version, migration_time)
  VALUES (191, NOW());
