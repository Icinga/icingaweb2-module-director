ALTER TABLE icinga_usergroup
  ADD COLUMN zone_id integer DEFAULT NULL,
  ADD CONSTRAINT icinga_usergroup_zone
  FOREIGN KEY (zone_id)
  REFERENCES icinga_zone (id)
    ON DELETE RESTRICT
    ON UPDATE CASCADE;

CREATE INDEX usergroup_zone ON icinga_usergroup (zone_id);


INSERT INTO director_schema_migration
  (schema_version, migration_time)
  VALUES (149, NOW());
