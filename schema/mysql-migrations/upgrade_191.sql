ALTER TABLE director_property
  MODIFY COLUMN value_type ENUM(
  'string',
  'number',
  'bool',
  'array',
  'dict',
  'fixed-array',
  'fixed-dictionary',
  'dynamic-array',
  'dynamic-dictionary'
  ) NOT NULL;

UPDATE director_property
SET value_type = CASE
               WHEN value_type = 'array' AND instantiable = 'n' THEN 'fixed-array'
               WHEN value_type = 'array' AND instantiable = 'y' THEN 'dynamic-array'
               WHEN value_type = 'dict' AND instantiable = 'n' THEN 'fixed-dictionary'
               WHEN value_type = 'dict' AND instantiable = 'y' THEN 'dynamic-dictionary'
               ELSE value_type
  END;

ALTER TABLE director_property
  MODIFY COLUMN value_type ENUM(
  'string',
  'number',
  'bool',
  'fixed-array',
  'fixed-dictionary',
  'dynamic-array',
  'dynamic-dictionary'
  ) NOT NULL;

ALTER TABLE icinga_host_var
  ADD COLUMN property_uuid varbinary(16) DEFAULT NULL;

ALTER TABLE director_property
  DROP COLUMN instantiable,
  ADD COLUMN description TEXT DEFAULT NULL;

INSERT INTO director_schema_migration
(schema_version, migration_time)
VALUES (191, NOW());