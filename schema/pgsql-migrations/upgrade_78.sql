CREATE TABLE icinga_user_field (
  user_id integer NOT NULL,
  datafield_id integer NOT NULL,
  is_required enum_boolean NOT NULL,
  PRIMARY KEY (user_id, datafield_id),
  CONSTRAINT icinga_user_field_user
  FOREIGN KEY (user_id)
    REFERENCES icinga_user (id)
  ON DELETE CASCADE
  ON UPDATE CASCADE,
  CONSTRAINT icinga_user_field_datafield
  FOREIGN KEY (datafield_id)
    REFERENCES director_datafield (id)
  ON DELETE CASCADE
  ON UPDATE CASCADE
);

CREATE UNIQUE INDEX user_field_key ON icinga_user_field (user_id, datafield_id);
CREATE INDEX user_field_user ON icinga_user_field (user_id);
CREATE INDEX user_field_datafield ON icinga_user_field (datafield_id);
COMMENT ON COLUMN icinga_user_field.user_id IS 'Makes only sense for templates';

INSERT INTO director_schema_migration
  (schema_version, migration_time)
  VALUES (78, NOW());
