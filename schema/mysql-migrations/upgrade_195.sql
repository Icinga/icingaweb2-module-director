ALTER TABLE director_property
  DROP INDEX unique_name_parent_uuid,
  DROP COLUMN parent_uuid_v;

ALTER TABLE director_property
  ADD COLUMN parent_uuid_v varbinary(16)
    AS (COALESCE(parent_uuid, 0x00000000000000000000000000000000)) VIRTUAL,
  ADD UNIQUE INDEX unique_name_parent_uuid (key_name, parent_uuid_v);

INSERT INTO director_schema_migration
(schema_version, migration_time)
VALUES (195, NOW());
