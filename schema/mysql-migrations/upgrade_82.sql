ALTER TABLE sync_rule
  MODIFY COLUMN object_type enum(
    'host',
    'service',
    'command',
    'user',
    'hostgroup',
    'servicegroup',
    'usergroup',
    'datalistEntry',
    'endpoint',
    'zone'
  ) NOT NULL;

INSERT INTO director_schema_migration
  SET migration_time = NOW(),
      schema_version = 82;
