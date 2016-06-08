CREATE TABLE icinga_hostgroup_assignment (
  id bigserial,
  hostgroup_id integer NOT NULL,
  filter_string TEXT NOT NULL,
  assign_type enum_assign_type NOT NULL DEFAULT 'assign',
  PRIMARY KEY (id),
  CONSTRAINT icinga_hostgroup_assignment
  FOREIGN KEY (hostgroup_id)
  REFERENCES icinga_hostgroup (id)
  ON DELETE CASCADE
  ON UPDATE CASCADE
);

-- INSERT INTO director_schema_migration
--   (schema_version, migration_time)
--   VALUES (X2, NOW());
