ALTER TABLE import_source
  ADD UNIQUE INDEX source_name (source_name);

ALTER TABLE sync_rule
  ADD UNIQUE INDEX rule_name (rule_name);

INSERT INTO director_schema_migration
  (schema_version, migration_time)
  VALUES (152, NOW());
