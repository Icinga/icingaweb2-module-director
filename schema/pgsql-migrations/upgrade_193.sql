ALTER TABLE icinga_hostgroup
  ADD COLUMN zone_id integer DEFAULT NULL;

ALTER TABLE icinga_hostgroup
  ADD CONSTRAINT icinga_hostgroup_zone
  FOREIGN KEY (zone_id)
  REFERENCES icinga_zone (id)
    ON DELETE RESTRICT
    ON UPDATE CASCADE;

CREATE INDEX hostgroup_zone ON icinga_hostgroup (zone_id);

ALTER TABLE icinga_servicegroup
  ADD COLUMN zone_id integer DEFAULT NULL;

ALTER TABLE icinga_servicegroup
  ADD CONSTRAINT icinga_servicegroup_zone
  FOREIGN KEY (zone_id)
  REFERENCES icinga_zone (id)
    ON DELETE RESTRICT
    ON UPDATE CASCADE;

CREATE INDEX servicegroup_zone ON icinga_servicegroup (zone_id);

INSERT INTO director_schema_migration
  (schema_version, migration_time)
  VALUES (193, NOW());
