ALTER TABLE director_basket_content
  ALTER COLUMN summary TYPE VARCHAR(500);

INSERT INTO director_schema_migration
  (schema_version, migration_time)
  VALUES (157, NOW());
