CREATE TABLE icinga_notification_field (
  notification_id integer NOT NULL,
  datafield_id integer NOT NULL,
  is_required enum_boolean NOT NULL,
  PRIMARY KEY (notification_id, datafield_id),
  CONSTRAINT icinga_notification_field_notification
  FOREIGN KEY (notification_id)
    REFERENCES icinga_notification (id)
  ON DELETE CASCADE
  ON UPDATE CASCADE,
  CONSTRAINT icinga_notification_field_datafield
  FOREIGN KEY (datafield_id)
    REFERENCES director_datafield (id)
  ON DELETE CASCADE
  ON UPDATE CASCADE
);

CREATE UNIQUE INDEX notification_field_key ON icinga_notification_field (notification_id, datafield_id);
CREATE INDEX notification_field_notification ON icinga_notification_field (notification_id);
CREATE INDEX notification_field_datafield ON icinga_notification_field (datafield_id);
COMMENT ON COLUMN icinga_notification_field.notification_id IS 'Makes only sense for templates';


INSERT INTO director_schema_migration
  (schema_version, migration_time)
  VALUES (117, NOW());
