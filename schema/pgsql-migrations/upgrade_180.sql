CREATE TABLE branched_icinga_service_set (
  uuid bytea NOT NULL UNIQUE CHECK(LENGTH(uuid) = 16),
  branch_uuid bytea NOT NULL CHECK(LENGTH(branch_uuid) = 16),
  branch_created enum_boolean NOT NULL DEFAULT 'n',
  branch_deleted enum_boolean NOT NULL DEFAULT 'n',

  object_name character varying(255) DEFAULT NULL,
  object_type enum_object_type_all DEFAULT NULL,
  disabled enum_boolean DEFAULT NULL,
  host character varying(255) DEFAULT NULL,
  description TEXT DEFAULT NULL,
  assign_filter text DEFAULT NULL,


  imports TEXT DEFAULT NULL,
  vars TEXT DEFAULT NULL,
  set_null TEXT DEFAULT NULL,
  PRIMARY KEY (branch_uuid, uuid),
  CONSTRAINT icinga_service_branch
      FOREIGN KEY (branch_uuid)
          REFERENCES director_branch (uuid)
          ON DELETE CASCADE
          ON UPDATE CASCADE
);

CREATE UNIQUE INDEX service_set_branch_object_name ON branched_icinga_service_set (branch_uuid, object_name);
CREATE INDEX branched_service_set_search_object_name ON branched_icinga_service_set (object_name);


INSERT INTO director_schema_migration
  (schema_version, migration_time)
  VALUES (180, NOW());
