CREATE TABLE icinga_dependency (
  id serial,
  object_name character varying(255) NOT NULL,
  object_type enum_object_type_all NOT NULL,
  disabled enum_boolean DEFAULT 'n',
  apply_to enum_host_service NULL DEFAULT NULL,
  parent_host_id integer DEFAULT NULL,
  parent_service_id integer DEFAULT NULL,
  child_host_id integer DEFAULT NULL,
  child_service_id integer DEFAULT NULL,
  disable_checks enum_boolean DEFAULT NULL,
  disable_notifications enum_boolean DEFAULT NULL,
  ignore_soft_states enum_boolean DEFAULT NULL,
  period_id integer DEFAULT NULL,
  zone_id integer DEFAULT NULL,
  assign_filter text DEFAULT NULL,
  parent_service_by_name character varying(255),
  PRIMARY KEY (id),
  CONSTRAINT icinga_dependency_parent_host
    FOREIGN KEY (parent_host_id)
    REFERENCES icinga_host (id)
    ON DELETE RESTRICT
    ON UPDATE CASCADE,
  CONSTRAINT icinga_dependency_parent_service
    FOREIGN KEY (parent_service_id)
    REFERENCES icinga_service (id)
    ON DELETE RESTRICT
    ON UPDATE CASCADE,
  CONSTRAINT icinga_dependency_child_host
    FOREIGN KEY (child_host_id)
    REFERENCES icinga_host (id)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT icinga_dependency_child_service
    FOREIGN KEY (child_service_id)
    REFERENCES icinga_service (id)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT icinga_dependency_period
    FOREIGN KEY (period_id)
    REFERENCES icinga_timeperiod (id)
    ON DELETE RESTRICT
    ON UPDATE CASCADE,
  CONSTRAINT icinga_dependency_zone
    FOREIGN KEY (zone_id)
    REFERENCES icinga_zone (id)
    ON DELETE RESTRICT
    ON UPDATE CASCADE
);

CREATE INDEX dependency_parent_host ON icinga_dependency (parent_host_id);
CREATE INDEX dependency_parent_service ON icinga_dependency (parent_service_id);
CREATE INDEX dependency_child_host ON icinga_dependency (child_host_id);
CREATE INDEX dependency_child_service ON icinga_dependency (child_service_id);
CREATE INDEX dependency_period ON icinga_dependency (period_id);
CREATE INDEX dependency_zone ON icinga_dependency (zone_id);


CREATE TABLE icinga_dependency_inheritance (
  dependency_id integer NOT NULL,
  parent_dependency_id integer NOT NULL,
  weight integer DEFAULT NULL,
  PRIMARY KEY (dependency_id, parent_dependency_id),
  CONSTRAINT icinga_dependency_inheritance_dependency
    FOREIGN KEY (dependency_id)
    REFERENCES icinga_dependency (id)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT icinga_dependency_inheritance_parent_dependency
    FOREIGN KEY (parent_dependency_id)
    REFERENCES icinga_dependency (id)
    ON DELETE RESTRICT
    ON UPDATE CASCADE
);

CREATE UNIQUE INDEX dependency_inheritance_unique_order ON icinga_dependency_inheritance (dependency_id, weight);
CREATE INDEX dependency_inheritance_dependency ON icinga_dependency_inheritance (dependency_id);
CREATE INDEX dependency_inheritance_dependency_parent ON icinga_dependency_inheritance (parent_dependency_id);


CREATE TABLE icinga_dependency_states_set (
  dependency_id integer NOT NULL,
  property enum_state_name NOT NULL,
  merge_behaviour enum_set_merge_behaviour NOT NULL DEFAULT 'override',
  PRIMARY KEY (dependency_id, property, merge_behaviour),
  CONSTRAINT icinga_dependency_states_set_dependency
    FOREIGN KEY (dependency_id)
    REFERENCES icinga_dependency (id)
    ON DELETE CASCADE
    ON UPDATE CASCADE
);

CREATE INDEX dependency_states_set_dependency ON icinga_dependency_states_set (dependency_id);
COMMENT ON COLUMN icinga_dependency_states_set.merge_behaviour IS 'override: = [], extend: += [], blacklist: -= []';


INSERT INTO director_schema_migration
  (schema_version, migration_time)
  VALUES (144, NOW());
