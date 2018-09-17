ALTER TABLE icinga_timeperiod
  ADD COLUMN prefer_includes enum_boolean DEFAULT NULL;

CREATE TABLE icinga_timeperiod_include (
  timeperiod_id integer NOT NULL,
  include_id integer NOT NULL,
  PRIMARY KEY (timeperiod_id, include_id),
  CONSTRAINT icinga_timeperiod_timeperiod_include
  FOREIGN KEY (include_id)
  REFERENCES icinga_timeperiod (id)
  ON DELETE RESTRICT
  ON UPDATE CASCADE,
  CONSTRAINT icinga_timeperiod_include
  FOREIGN KEY (timeperiod_id)
  REFERENCES icinga_timeperiod (id)
  ON DELETE CASCADE
  ON UPDATE CASCADE
);

CREATE TABLE icinga_timeperiod_exclude (
  timeperiod_id integer NOT NULL,
  exclude_id integer NOT NULL,
  PRIMARY KEY (timeperiod_id, exclude_id),
  CONSTRAINT icinga_timeperiod_timeperiod_exclude
  FOREIGN KEY (exclude_id)
  REFERENCES icinga_timeperiod (id)
  ON DELETE RESTRICT
  ON UPDATE CASCADE,
  CONSTRAINT icinga_timeperiod_exclude
  FOREIGN KEY (timeperiod_id)
  REFERENCES icinga_timeperiod (id)
  ON DELETE CASCADE
  ON UPDATE CASCADE
);

INSERT INTO director_schema_migration
  (schema_version, migration_time)
VALUES (151, NOW());
