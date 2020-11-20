ALTER TABLE icinga_servicegroup
  ADD COLUMN zone_id INT(10) UNSIGNED DEFAULT NULL,
  ADD CONSTRAINT icinga_servicegroup_zone
    FOREIGN KEY zone (zone_id)
    REFERENCES icinga_zone (id)
      ON DELETE RESTRICT
      ON UPDATE CASCADE;

INSERT INTO director_schema_migration
  (schema_version, migration_time)
  VALUES (172, NOW());
