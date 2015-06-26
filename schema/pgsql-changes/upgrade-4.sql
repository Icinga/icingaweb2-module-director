CREATE TABLE icinga_user_inheritance (
  user_id integer NOT NULL,
  parent_user_id integer NOT NULL,
  weight integer DEFAULT NULL,
  PRIMARY KEY (user_id, parent_user_id),
  CONSTRAINT icinga_user_inheritance_user
  FOREIGN KEY (user_id)
  REFERENCES icinga_user (id)
  ON DELETE CASCADE
  ON UPDATE CASCADE,
  CONSTRAINT icinga_user_inheritance_parent_user
  FOREIGN KEY (parent_user_id)
  REFERENCES icinga_user (id)
  ON DELETE RESTRICT
  ON UPDATE CASCADE
);

CREATE UNIQUE INDEX user_inheritance_unique_order ON icinga_user_inheritance (user_id, weight);
CREATE INDEX user_inheritance_user ON icinga_user_inheritance (user_id);
CREATE INDEX user_inheritance_user_parent ON icinga_user_inheritance (parent_user_id);