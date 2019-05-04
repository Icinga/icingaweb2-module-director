ALTER TABLE icinga_timeperiod
  ADD COLUMN prefer_includes ENUM('y', 'n') DEFAULT NULL;

CREATE TABLE icinga_timeperiod_include (
  timeperiod_id INT(10) UNSIGNED NOT NULL,
  include_id INT(10) UNSIGNED NOT NULL,
  PRIMARY KEY (timeperiod_id, include_id),
  CONSTRAINT icinga_timeperiod_include
  FOREIGN KEY timeperiod (include_id)
  REFERENCES icinga_timeperiod (id)
    ON DELETE RESTRICT
    ON UPDATE RESTRICT,
  CONSTRAINT icinga_timeperiod_include_timeperiod
  FOREIGN KEY include (timeperiod_id)
  REFERENCES icinga_timeperiod (id)
    ON DELETE CASCADE
    ON UPDATE CASCADE
);

CREATE TABLE icinga_timeperiod_exclude (
  timeperiod_id INT(10) UNSIGNED NOT NULL,
  exclude_id INT(10) UNSIGNED NOT NULL,
  PRIMARY KEY (timeperiod_id, exclude_id),
  CONSTRAINT icinga_timeperiod_exclude
  FOREIGN KEY timeperiod (exclude_id)
  REFERENCES icinga_timeperiod (id)
    ON DELETE RESTRICT
    ON UPDATE RESTRICT,
  CONSTRAINT icinga_timeperiod_exclude_timeperiod
  FOREIGN KEY exclude (timeperiod_id)
  REFERENCES icinga_timeperiod (id)
    ON DELETE CASCADE
    ON UPDATE CASCADE
);

INSERT INTO director_schema_migration
  (schema_version, migration_time)
  VALUES (151, NOW());
