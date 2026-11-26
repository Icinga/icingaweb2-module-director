ALTER TYPE enum_property_value_type ADD VALUE 'director-object' AFTER 'datalist-non-strict';

CREATE TABLE director_property_object (
  property_uuid bytea CHECK(LENGTH(property_uuid) = 16) NOT NULL,
  object_type character varying(32) NOT NULL,
  PRIMARY KEY (property_uuid),
  CONSTRAINT director_property_object_property
    FOREIGN KEY (property_uuid)
      REFERENCES director_property (uuid)
      ON DELETE CASCADE
      ON UPDATE CASCADE
);

INSERT INTO director_schema_migration
  (schema_version, migration_time)
  VALUES (196, NOW());
