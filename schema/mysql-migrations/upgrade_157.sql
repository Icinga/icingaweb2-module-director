ALTER TABLE director_basket_content
  MODIFY COLUMN summary VARCHAR(500) NOT NULL;

  INSERT INTO director_schema_migration
  (schema_version, migration_time)
  VALUES (157, NOW());
