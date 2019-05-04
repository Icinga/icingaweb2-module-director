UPDATE import_row_modifier SET priority = id;

ALTER TABLE import_row_modifier ADD UNIQUE INDEX idx_prio (source_id, priority);

INSERT INTO director_schema_migration
  (schema_version, migration_time)
  VALUES (139, NOW());
