DROP INDEX IF EXISTS import_row_modifier_prio;
DROP INDEX IF EXISTS service_branch_object_name;

INSERT INTO director_schema_migration
  (schema_version, migration_time)
  VALUES (188, NOW());
