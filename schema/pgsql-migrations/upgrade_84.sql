ALTER TABLE icinga_usergroup DROP COLUMN zone_id;

INSERT INTO director_schema_migration
  (schema_version, migration_time)
  VALUES (84, NOW());
