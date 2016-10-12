ALTER TABLE sync_rule MODIFY object_type enum(
  'host',
  'service',
  'command',
  'user',
  'hostgroup',
  'servicegroup',
  'usergroup',
  'datalistEntry',
  'endpoint',
  'zone',
  'timePeriod',
  'serviceSet'
) NOT NULL;

INSERT INTO director_schema_migration
  (schema_version, migration_time)
  VALUES (116, NOW());
