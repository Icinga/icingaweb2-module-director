CREATE TYPE enum_owner_type AS ENUM('user', 'usergroup', 'role');

CREATE TABLE director_basket (
  uuid bytea CHECK(LENGTH(uuid) = 16) NOT NULL,
  basket_name VARCHAR(64) NOT NULL,
  owner_type enum_owner_type NOT NULL,
  owner_value VARCHAR(255) NOT NULL,
  objects text NOT NULL, -- json-encoded
  PRIMARY KEY (uuid)
);

CREATE UNIQUE INDEX basket_basket_name ON director_basket (basket_name);


CREATE TABLE director_basket_content (
  checksum bytea CHECK(LENGTH(checksum) = 20) NOT NULL,
  summary VARCHAR(255) NOT NULL, -- json
  content text NOT NULL, -- json
  PRIMARY KEY (checksum)
);


CREATE TABLE director_basket_snapshot (
  basket_uuid bytea CHECK(LENGTH(basket_uuid) = 16) NOT NULL,
  ts_create bigint NOT NULL,
  content_checksum bytea CHECK(LENGTH(content_checksum) = 20) NOT NULL,
  PRIMARY KEY (basket_uuid, ts_create),
  CONSTRAINT basked_snapshot_basket
  FOREIGN KEY (basket_uuid)
  REFERENCES director_basket (uuid)
    ON DELETE CASCADE
    ON UPDATE RESTRICT,
  CONSTRAINT basked_snapshot_content
  FOREIGN KEY (content_checksum)
  REFERENCES director_basket_content (checksum)
    ON DELETE RESTRICT
    ON UPDATE RESTRICT
);

CREATE INDEX basket_snapshot_sort_idx ON director_basket_snapshot (ts_create);


INSERT INTO director_schema_migration
  (schema_version, migration_time)
  VALUES (153, NOW());
