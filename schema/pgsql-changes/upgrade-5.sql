CREATE TABLE icinga_timeperiod_inheritance (
  timeperiod_id integer NOT NULL,
  parent_timeperiod_id integer NOT NULL,
  weight integer DEFAULT NULL,
  PRIMARY KEY (timeperiod_id, parent_timeperiod_id),
  CONSTRAINT icinga_timeperiod_inheritance_timeperiod
  FOREIGN KEY (timeperiod_id)
  REFERENCES icinga_timeperiod (id)
  ON DELETE CASCADE
  ON UPDATE CASCADE,
  CONSTRAINT icinga_timeperiod_inheritance_parent_timeperiod
  FOREIGN KEY (parent_timeperiod_id)
  REFERENCES icinga_timeperiod (id)
  ON DELETE RESTRICT
  ON UPDATE CASCADE
);

CREATE UNIQUE INDEX timeperiod_inheritance_unique_order ON icinga_timeperiod_inheritance (timeperiod_id, weight);
CREATE INDEX timeperiod_inheritance_timeperiod ON icinga_timeperiod_inheritance (timeperiod_id);
CREATE INDEX timeperiod_inheritance_timeperiod_parent ON icinga_timeperiod_inheritance (parent_timeperiod_id);