ALTER TABLE director_generated_file
  ADD COLUMN cnt_apply SMALLINT NOT NULL DEFAULT 0;

UPDATE director_generated_file
SET cnt_apply = ROUND(
  (LENGTH(content) - LENGTH( REPLACE(content, 'apply ', '') ) )
  / LENGTH('apply ')
);

INSERT INTO director_schema_migration
  (schema_version, migration_time)
  VALUES (122, NOW());
