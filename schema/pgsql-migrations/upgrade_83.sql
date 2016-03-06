ALTER TABLE icinga_command ALTER COLUMN command TYPE text;
ALTER TABLE icinga_command ALTER COLUMN command DROP DEFAULT;
ALTER TABLE icinga_command ALTER COLUMN command SET DEFAULT NULL;

INSERT INTO director_schema_migration
  (schema_version, migration_time)
  VALUES (83, NOW());
