CREATE TABLE director_basket (
  uuid VARBINARY(16) NOT NULL,
  basket_name VARCHAR(64) NOT NULL,
  owner_type ENUM(
    'user',
    'usergroup',
    'role'
  ) NOT NULL,
  owner_value VARCHAR(255) NOT NULL,
  objects MEDIUMTEXT NOT NULL, -- json-encoded
  PRIMARY KEY (uuid),
  UNIQUE INDEX basket_name (basket_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_bin;

CREATE TABLE director_basket_content (
  checksum VARBINARY(20) NOT NULL,
  summary VARCHAR(255) NOT NULL, -- json
  content MEDIUMTEXT NOT NULL, -- json
  PRIMARY KEY (checksum)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_bin;

CREATE TABLE director_basket_snapshot (
  basket_uuid VARBINARY(16) NOT NULL,
  ts_create BIGINT(20) NOT NULL,
  content_checksum VARBINARY(20) NOT NULL,
  PRIMARY KEY (basket_uuid, ts_create),
  INDEX sort_idx (ts_create),
  CONSTRAINT basked_snapshot_basket
  FOREIGN KEY director_basket_snapshot (basket_uuid)
  REFERENCES director_basket (uuid)
    ON DELETE CASCADE
    ON UPDATE RESTRICT,
  CONSTRAINT basked_snapshot_content
  FOREIGN KEY content_checksum (content_checksum)
  REFERENCES director_basket_content (checksum)
    ON DELETE RESTRICT
    ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_bin;

INSERT INTO director_schema_migration
  (schema_version, migration_time)
  VALUES (153, NOW());
