ALTER TABLE director_activity_log
  ADD INDEX search_author (author);

INSERT INTO director_schema_migration
  (schema_version, migration_time)
  VALUES (128, NOW());
