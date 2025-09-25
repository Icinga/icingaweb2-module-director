CREATE TABLE director_property (
                                   uuid binary(16) NOT NULL,
                                   parent_uuid binary(16) NULL DEFAULT NULL,
                                   key_name varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
                                   label varchar(255) COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
                                   value_type enum('string', 'number', 'bool', 'fixed-array', 'fixed-dictionary', 'dynamic-array', 'dynamic-dictionary') COLLATE utf8mb4_unicode_ci NOT NULL,
                                   description text,
                                   instantiable enum('y', 'n') NOT NULL DEFAULT 'n',
                                   PRIMARY KEY (uuid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;

CREATE TABLE icinga_host_property (
                                      host_uuid binary(16) NOT NULL,
                                      property_uuid binary(16) NOT NULL,
                                      required enum('y', 'n') NOT NULL DEFAULT 'n',
                                      PRIMARY KEY (host_uuid, property_uuid),
                                      CONSTRAINT icinga_host_property_host
                                          FOREIGN KEY host(host_uuid)
                                              REFERENCES icinga_host (uuid)
                                              ON DELETE CASCADE
                                              ON UPDATE CASCADE,
                                      CONSTRAINT icinga_host_custom_property
                                          FOREIGN KEY property(property_uuid)
                                              REFERENCES director_property (uuid)
                                              ON DELETE CASCADE
                                              ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;

ALTER TABLE icinga_host_var
  ADD COLUMN property_uuid binary(16);

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
VALUES (192, NOW());
