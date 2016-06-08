CREATE TABLE icinga_hostgroup_assignment (
  id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  hostgroup_id INT(10) UNSIGNED NOT NULL,
  filter_string TEXT NOT NULL,
  assign_type ENUM('assign', 'ignore') NOT NULL DEFAULT 'assign',
  PRIMARY KEY (id),
  CONSTRAINT icinga_hostgroup_assignment
  FOREIGN KEY hostgroup (hostgroup_id)
  REFERENCES icinga_hostgroup (id)
    ON DELETE CASCADE
    ON UPDATE CASCADE
) ENGINE=InnoDB;

-- INSERT INTO director_schema_migration
--   (schema_version, migration_time)
--   VALUES (X2, NOW());
