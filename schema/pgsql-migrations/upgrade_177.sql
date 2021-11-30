ALTER TABLE icinga_service_set ADD COLUMN uuid bytea UNIQUE CHECK(LENGTH(uuid) = 16);
UPDATE icinga_service_set SET uuid = decode(replace(gen_random_uuid()::text, '-', ''), 'hex') WHERE uuid IS NULL;
ALTER TABLE icinga_service_set ALTER COLUMN uuid SET NOT NULL;
CREATE UNIQUE INDEX service_set_uuid ON icinga_service_set (uuid);

INSERT INTO director_schema_migration
(schema_version, migration_time)
VALUES (177, NOW());
