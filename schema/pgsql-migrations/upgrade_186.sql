ALTER TABLE director_datafield ADD COLUMN uuid bytea UNIQUE CHECK(LENGTH(uuid) = 16);
UPDATE director_datafield SET uuid = decode(replace(gen_random_uuid()::text, '-', ''), 'hex') WHERE uuid IS NULL;
ALTER TABLE director_datafield ALTER COLUMN uuid SET NOT NULL;

ALTER TABLE director_datalist ADD COLUMN uuid bytea UNIQUE CHECK(LENGTH(uuid) = 16);
UPDATE director_datalist SET uuid = decode(replace(gen_random_uuid()::text, '-', ''), 'hex') WHERE uuid IS NULL;
ALTER TABLE director_datalist ALTER COLUMN uuid SET NOT NULL;

INSERT INTO director_schema_migration
(schema_version, migration_time)
VALUES (186, NOW());
