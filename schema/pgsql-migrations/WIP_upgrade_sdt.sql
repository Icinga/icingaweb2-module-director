CREATE TABLE icinga_scheduled_downtime (
  id serial,
  object_name character varying(255) NOT NULL,
  display_name character varying(255) DEFAULT NULL,
  zone_id integer DEFAULT NULL,
  object_type enum_object_type_all NOT NULL,
  disabled enum_boolean NOT NULL DEFAULT 'n',
  assign_filter text DEFAULT NULL,
  author character varying(255) DEFAULT NULL,
  comment text DEFAULT NULL,
  fixed enum_boolean DEFAULT NULL,
  duration int DEFAULT NULL,
  PRIMARY KEY (id),
  CONSTRAINT icinga_scheduled_downtime_zone
  FOREIGN KEY (zone_id)
  REFERENCES icinga_zone (id)
    ON DELETE RESTRICT
    ON UPDATE CASCADE
);

CREATE UNIQUE INDEX scheduled_downtime_object_name ON icinga_scheduled_downtime (object_name);
CREATE INDEX scheduled_downtime_zone ON icinga_scheduled_downtime (zone_id);


CREATE TABLE icinga_scheduled_downtime_inheritance (
  scheduled_downtime_id integer NOT NULL,
  parent_scheduled_downtime_id integer NOT NULL,
  weight integer DEFAULT NULL,
  PRIMARY KEY (scheduled_downtime_id, parent_scheduled_downtime_id),
  CONSTRAINT icinga_scheduled_downtime_inheritance_scheduled_downtime
  FOREIGN KEY (scheduled_downtime_id)
  REFERENCES icinga_scheduled_downtime (id)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT icinga_scheduled_downtime_inheritance_parent_scheduled_downtime
  FOREIGN KEY (parent_scheduled_downtime_id)
  REFERENCES icinga_scheduled_downtime (id)
    ON DELETE RESTRICT
    ON UPDATE CASCADE
);

CREATE UNIQUE INDEX scheduled_downtime_inheritance_unique_order ON icinga_scheduled_downtime_inheritance (scheduled_downtime_id, weight);
CREATE INDEX scheduled_downtime_inheritance_scheduled_downtime ON icinga_scheduled_downtime_inheritance (scheduled_downtime_id);
CREATE INDEX scheduled_downtime_inheritance_scheduled_downtime_parent ON icinga_scheduled_downtime_inheritance (parent_scheduled_downtime_id);


CREATE TABLE icinga_scheduled_downtime_range (
  scheduled_downtime_id serial,
  range_key character varying(255) NOT NULL,
  range_value character varying(255) NOT NULL,
  range_type enum_timeperiod_range_type NOT NULL DEFAULT 'include',
  merge_behaviour enum_merge_behaviour NOT NULL DEFAULT 'set',
  PRIMARY KEY (scheduled_downtime_id, range_type, range_key),
  CONSTRAINT icinga_scheduled_downtime_range_scheduled_downtime
  FOREIGN KEY (scheduled_downtime_id)
  REFERENCES icinga_scheduled_downtime (id)
    ON DELETE CASCADE
    ON UPDATE CASCADE
);

CREATE INDEX scheduled_downtime_range_scheduled_downtime ON icinga_scheduled_downtime_range (scheduled_downtime_id);
COMMENT ON COLUMN icinga_scheduled_downtime_range.range_key IS 'monday, ...';
COMMENT ON COLUMN icinga_scheduled_downtime_range.range_value IS '00:00-24:00, ...';
COMMENT ON COLUMN icinga_scheduled_downtime_range.range_type IS 'include -> ranges {}, exclude ranges_ignore {} - not yet';
COMMENT ON COLUMN icinga_scheduled_downtime_range.merge_behaviour IS 'set -> = {}, add -> += {}, substract -> -= {}';


-- INSERT INTO director_schema_migration
--   (schema_version, migration_time)
--   VALUES (149, NOW());
