CREATE TABLE branched_icinga_service_set (
  uuid VARBINARY(16) NOT NULL,
  branch_uuid VARBINARY(16) NOT NULL,
  branch_created ENUM('y', 'n') NOT NULL DEFAULT 'n',
  branch_deleted ENUM('y', 'n') NOT NULL DEFAULT 'n',

  object_name VARCHAR(128) DEFAULT NULL,
  object_type ENUM('object', 'template', 'external_object') DEFAULT NULL,
  host VARCHAR(255) DEFAULT NULL,
  description TEXT DEFAULT NULL,
  assign_filter TEXT DEFAULT NULL,

  imports TEXT DEFAULT NULL,
  set_null TEXT DEFAULT NULL,
  PRIMARY KEY (branch_uuid, uuid),
  INDEX search_object_name (object_name),
  CONSTRAINT icinga_service_set_branch
    FOREIGN KEY branch (branch_uuid)
    REFERENCES director_branch (uuid)
    ON DELETE CASCADE
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

INSERT INTO director_schema_migration
  (schema_version, migration_time)
  VALUES (180, NOW());
