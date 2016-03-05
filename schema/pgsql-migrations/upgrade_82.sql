ALTER TYPE enum_sync_rule_object_type ADD VALUE 'service' AFTER 'host';
ALTER TYPE enum_sync_rule_object_type ADD VALUE 'command' AFTER 'service';
ALTER TYPE enum_sync_rule_object_type ADD VALUE 'hostgroup';
ALTER TYPE enum_sync_rule_object_type ADD VALUE 'servicegroup';
ALTER TYPE enum_sync_rule_object_type ADD VALUE 'usergroup';
ALTER TYPE enum_sync_rule_object_type ADD VALUE 'datalistEntry';
ALTER TYPE enum_sync_rule_object_type ADD VALUE 'endpoint';
ALTER TYPE enum_sync_rule_object_type ADD VALUE 'zone';

INSERT INTO director_schema_migration
  (schema_version, migration_time)
  VALUES (82, NOW());
