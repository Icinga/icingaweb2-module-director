ALTER TYPE enum_merge_behaviour ADD VALUE 'override';


CREATE TABLE icinga_notification_states_set (
  notification_id integer NOT NULL,
  property enum_state_name NOT NULL,
  merge_behaviour enum_merge_behaviour NOT NULL DEFAULT 'override',
  PRIMARY KEY (notification_id, property, merge_behaviour),
  CONSTRAINT icinga_notification_states_set_notification
    FOREIGN KEY (notification_id)
    REFERENCES icinga_notification (id)
    ON DELETE CASCADE
    ON UPDATE CASCADE
);

COMMENT ON COLUMN icinga_notification_states_set.merge_behaviour IS 'override: = [], extend: += [], blacklist: -= []';


CREATE TABLE icinga_notification_types_set (
  notification_id integer NOT NULL,
  property enum_type_name NOT NULL,
  merge_behaviour enum_merge_behaviour NOT NULL DEFAULT 'override',
  PRIMARY KEY (notification_id, property, merge_behaviour),
  CONSTRAINT icinga_notification_types_set_notification
    FOREIGN KEY (notification_id)
    REFERENCES icinga_notification (id)
    ON DELETE CASCADE
    ON UPDATE CASCADE
);

COMMENT ON COLUMN icinga_notification_types_set.merge_behaviour IS 'override: = [], extend: += [], blacklist: -= []';


CREATE TABLE icinga_notification_var (
  notification_id integer NOT NULL,
  varname VARCHAR(255) DEFAULT NULL,
  varvalue TEXT DEFAULT NULL,
  format enum_property_format,
  PRIMARY KEY (notification_id, varname),
  CONSTRAINT icinga_notification_var_notification
    FOREIGN KEY (notification_id)
    REFERENCES icinga_notification (id)
    ON DELETE CASCADE
    ON UPDATE CASCADE
);

CREATE UNIQUE INDEX notification_var_search_idx ON icinga_notification_var (varname);


CREATE TABLE icinga_notification_inheritance (
  notification_id integer NOT NULL,
  parent_notification_id integer NOT NULL,
  weight integer DEFAULT NULL,
  PRIMARY KEY (notification_id, parent_notification_id),
  CONSTRAINT icinga_notification_inheritance_notification
  FOREIGN KEY (notification_id)
  REFERENCES icinga_notification (id)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT icinga_notification_inheritance_parent_notification
  FOREIGN KEY (parent_notification_id)
  REFERENCES icinga_notification (id)
    ON DELETE RESTRICT
    ON UPDATE CASCADE
);

CREATE UNIQUE INDEX notification_inheritance ON icinga_notification_inheritance (notification_id, weight);


INSERT INTO director_schema_migration
  (schema_version, migration_time)
  VALUES (77, NOW());
