ALTER TABLE import_source
  ADD COLUMN description TEXT DEFAULT NULL;

ALTER TABLE sync_rule
  ADD COLUMN description TEXT DEFAULT NULL;

INSERT INTO director_schema_migration
(schema_version, migration_time)
VALUES (137, NOW());
