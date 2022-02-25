ALTER TABLE icinga_zone DROP COLUMN IF EXISTS uuid;

ALTER TABLE icinga_zone ADD COLUMN uuid bytea UNIQUE CHECK(LENGTH(uuid) = 16);
UPDATE icinga_zone SET uuid = decode(replace(gen_random_uuid()::text, '-', ''), 'hex') WHERE uuid IS NULL;
ALTER TABLE icinga_zone ALTER COLUMN uuid SET NOT NULL;

ALTER TABLE icinga_timeperiod ADD COLUMN uuid bytea UNIQUE CHECK(LENGTH(uuid) = 16);
UPDATE icinga_timeperiod SET uuid = decode(replace(gen_random_uuid()::text, '-', ''), 'hex') WHERE uuid IS NULL;
ALTER TABLE icinga_timeperiod ALTER COLUMN uuid SET NOT NULL;

ALTER TABLE icinga_command ADD COLUMN uuid bytea UNIQUE CHECK(LENGTH(uuid) = 16);
UPDATE icinga_command SET uuid = decode(replace(gen_random_uuid()::text, '-', ''), 'hex') WHERE uuid IS NULL;
ALTER TABLE icinga_command ALTER COLUMN uuid SET NOT NULL;

ALTER TABLE icinga_apiuser ADD COLUMN uuid bytea UNIQUE CHECK(LENGTH(uuid) = 16);
UPDATE icinga_apiuser SET uuid = decode(replace(gen_random_uuid()::text, '-', ''), 'hex') WHERE uuid IS NULL;
ALTER TABLE icinga_apiuser ALTER COLUMN uuid SET NOT NULL;

ALTER TABLE icinga_endpoint ADD COLUMN uuid bytea UNIQUE CHECK(LENGTH(uuid) = 16);
UPDATE icinga_endpoint SET uuid = decode(replace(gen_random_uuid()::text, '-', ''), 'hex') WHERE uuid IS NULL;
ALTER TABLE icinga_endpoint ALTER COLUMN uuid SET NOT NULL;

ALTER TABLE icinga_host ADD COLUMN uuid bytea UNIQUE CHECK(LENGTH(uuid) = 16);
UPDATE icinga_host SET uuid = decode(replace(gen_random_uuid()::text, '-', ''), 'hex') WHERE uuid IS NULL;
ALTER TABLE icinga_host ALTER COLUMN uuid SET NOT NULL;

ALTER TABLE icinga_service ADD COLUMN uuid bytea UNIQUE CHECK(LENGTH(uuid) = 16);
UPDATE icinga_service SET uuid = decode(replace(gen_random_uuid()::text, '-', ''), 'hex') WHERE uuid IS NULL;
ALTER TABLE icinga_service ALTER COLUMN uuid SET NOT NULL;

ALTER TABLE icinga_hostgroup ADD COLUMN uuid bytea UNIQUE CHECK(LENGTH(uuid) = 16);
UPDATE icinga_hostgroup SET uuid = decode(replace(gen_random_uuid()::text, '-', ''), 'hex') WHERE uuid IS NULL;
ALTER TABLE icinga_hostgroup ALTER COLUMN uuid SET NOT NULL;

ALTER TABLE icinga_servicegroup ADD COLUMN uuid bytea UNIQUE CHECK(LENGTH(uuid) = 16);
UPDATE icinga_servicegroup SET uuid = decode(replace(gen_random_uuid()::text, '-', ''), 'hex') WHERE uuid IS NULL;
ALTER TABLE icinga_servicegroup ALTER COLUMN uuid SET NOT NULL;

ALTER TABLE icinga_user ADD COLUMN uuid bytea UNIQUE CHECK(LENGTH(uuid) = 16);
UPDATE icinga_user SET uuid = decode(replace(gen_random_uuid()::text, '-', ''), 'hex') WHERE uuid IS NULL;
ALTER TABLE icinga_user ALTER COLUMN uuid SET NOT NULL;

ALTER TABLE icinga_usergroup ADD COLUMN uuid bytea UNIQUE CHECK(LENGTH(uuid) = 16);
UPDATE icinga_usergroup SET uuid = decode(replace(gen_random_uuid()::text, '-', ''), 'hex') WHERE uuid IS NULL;
ALTER TABLE icinga_usergroup ALTER COLUMN uuid SET NOT NULL;

ALTER TABLE icinga_notification ADD COLUMN uuid bytea UNIQUE CHECK(LENGTH(uuid) = 16);
UPDATE icinga_notification SET uuid = decode(replace(gen_random_uuid()::text, '-', ''), 'hex') WHERE uuid IS NULL;
ALTER TABLE icinga_notification ALTER COLUMN uuid SET NOT NULL;

ALTER TABLE icinga_dependency ADD COLUMN uuid bytea UNIQUE CHECK(LENGTH(uuid) = 16);
UPDATE icinga_dependency SET uuid = decode(replace(gen_random_uuid()::text, '-', ''), 'hex') WHERE uuid IS NULL;
ALTER TABLE icinga_dependency ALTER COLUMN uuid SET NOT NULL;

ALTER TABLE icinga_scheduled_downtime ADD COLUMN uuid bytea UNIQUE CHECK(LENGTH(uuid) = 16);
UPDATE icinga_scheduled_downtime SET uuid = decode(replace(gen_random_uuid()::text, '-', ''), 'hex') WHERE uuid IS NULL;
ALTER TABLE icinga_scheduled_downtime ALTER COLUMN uuid SET NOT NULL;

INSERT INTO director_schema_migration
(schema_version, migration_time)
VALUES (174, NOW());
