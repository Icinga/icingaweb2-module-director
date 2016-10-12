ALTER TYPE enum_sync_rule_object_type ADD VALUE 'timePeriod';
ALTER TYPE enum_sync_rule_object_type ADD VALUE 'serviceSet';

INSERT INTO director_schema_migration
  (schema_version, migration_time)
  VALUES (116, NOW());
