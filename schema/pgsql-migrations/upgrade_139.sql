UPDATE import_row_modifier SET priority = id;

CREATE UNIQUE INDEX import_row_modifier_prio
  ON import_row_modifier (source_id, priority);


INSERT INTO director_schema_migration
  (schema_version, migration_time)
  VALUES (139, NOW());
