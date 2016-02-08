CREATE TABLE director_schema_migration (
  schema_version SMALLINT UNSIGNED NOT NULL,
  migration_time DATETIME NOT NULL,
  PRIMARY KEY(schema_version)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE director_dbversion;

INSERT INTO director_schema_migration
  SET migration_time = NOW(),
      schema_version = 63;

