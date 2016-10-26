ALTER TABLE icinga_service
  ADD COLUMN apply_for character varying(255) DEFAULT NULL;

INSERT INTO director_schema_migration
  (schema_version, migration_time)
  VALUES (119, NOW());
