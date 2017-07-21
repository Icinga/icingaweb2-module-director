CREATE TABLE icinga_hostgroup_host_resolved (
  hostgroup_id INT(10) UNSIGNED NOT NULL,
  host_id INT(10) UNSIGNED NOT NULL,
  PRIMARY KEY (hostgroup_id, host_id),
  CONSTRAINT icinga_hostgroup_host_resolved_host
  FOREIGN KEY host (host_id)
  REFERENCES icinga_host (id)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT icinga_hostgroup_host_resolved_hostgroup
  FOREIGN KEY hostgroup (hostgroup_id)
  REFERENCES icinga_hostgroup (id)
    ON DELETE CASCADE
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

INSERT INTO director_schema_migration
(schema_version, migration_time)
VALUES (131, NOW());
