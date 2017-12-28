ALTER TABLE icinga_hostgroup
  ADD COLUMN zone_id integer DEFAULT NULL;

ALTER TABLE icinga_hostgroup
  ADD CONSTRAINT icinga_hostgroup_zone
    FOREIGN KEY (zone_id)
    REFERENCES icinga_zone (id)
    ON DELETE RESTRICT
    ON UPDATE CASCADE;

INSERT INTO director_schema_migration
  (schema_version, migration_time)
  VALUES (146, NOW());
