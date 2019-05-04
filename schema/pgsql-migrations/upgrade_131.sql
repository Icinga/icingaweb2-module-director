CREATE TABLE icinga_hostgroup_host_resolved (
  hostgroup_id integer NOT NULL,
  host_id integer NOT NULL,
  PRIMARY KEY (hostgroup_id, host_id),
  CONSTRAINT icinga_hostgroup_host_resolved_host
  FOREIGN KEY (host_id)
    REFERENCES icinga_host (id)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT icinga_hostgroup_host_resolved_hostgroup
  FOREIGN KEY (hostgroup_id)
    REFERENCES icinga_hostgroup (id)
    ON DELETE CASCADE
    ON UPDATE CASCADE
);

CREATE INDEX hostgroup_host_resolved_host ON icinga_hostgroup_host_resolved (host_id);
CREATE INDEX hostgroup_host_resolved_hostgroup ON icinga_hostgroup_host_resolved (hostgroup_id);

INSERT INTO director_schema_migration
  (schema_version, migration_time)
  VALUES (131, NOW());
