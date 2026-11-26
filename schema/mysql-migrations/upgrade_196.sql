ALTER TABLE director_property
  MODIFY COLUMN value_type enum(
    'string',
    'number',
    'bool',
    'sensitive',
    'fixed-array',
    'dynamic-array',
    'fixed-dictionary',
    'dynamic-dictionary',
    'datalist-strict',
    'datalist-non-strict',
    'director-object'
  ) COLLATE utf8mb4_unicode_ci NOT NULL;

CREATE TABLE director_property_object (
  property_uuid varbinary(16) NOT NULL,
  object_type varchar(32) NOT NULL,
  PRIMARY KEY (property_uuid),
  CONSTRAINT director_property_object_property
    FOREIGN KEY property (property_uuid)
    REFERENCES director_property (uuid)
    ON DELETE CASCADE
    ON UPDATE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_bin;

INSERT INTO director_schema_migration
  (schema_version, migration_time)
  VALUES (196, NOW());
