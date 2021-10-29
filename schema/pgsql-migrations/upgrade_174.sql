ALTER TABLE icinga_zone ADD COLUMN uuid bytea UNIQUE CHECK(LENGTH(uuid) = 16);
UPDATE icinga_zone SET uuid = decode(replace(gen_random_uuid()::text, '-', ''), 'hex') WHERE uuid IS NULL;
ALTER TABLE icinga_zone ALTER COLUMN uuid SET NOT NULL;
CREATE UNIQUE INDEX zone_uuid ON icinga_zone (uuid);

ALTER TABLE icinga_timeperiod ADD COLUMN uuid bytea UNIQUE CHECK(LENGTH(uuid) = 16);
UPDATE icinga_timeperiod SET uuid = decode(replace(gen_random_uuid()::text, '-', ''), 'hex') WHERE uuid IS NULL;
ALTER TABLE icinga_timeperiod ALTER COLUMN uuid SET NOT NULL;
CREATE UNIQUE INDEX timeperiod_uuid ON icinga_timeperiod (uuid);

ALTER TABLE icinga_command ADD COLUMN uuid bytea UNIQUE CHECK(LENGTH(uuid) = 16);
UPDATE icinga_command SET uuid = decode(replace(gen_random_uuid()::text, '-', ''), 'hex') WHERE uuid IS NULL;
ALTER TABLE icinga_command ALTER COLUMN uuid SET NOT NULL;
CREATE UNIQUE INDEX command_uuid ON icinga_command (uuid);

ALTER TABLE icinga_apiuser ADD COLUMN uuid bytea UNIQUE CHECK(LENGTH(uuid) = 16);
UPDATE icinga_apiuser SET uuid = decode(replace(gen_random_uuid()::text, '-', ''), 'hex') WHERE uuid IS NULL;
ALTER TABLE icinga_apiuser ALTER COLUMN uuid SET NOT NULL;
CREATE UNIQUE INDEX apiuser_uuid ON icinga_apiuser (uuid);

ALTER TABLE icinga_endpoint ADD COLUMN uuid bytea UNIQUE CHECK(LENGTH(uuid) = 16);
UPDATE icinga_endpoint SET uuid = decode(replace(gen_random_uuid()::text, '-', ''), 'hex') WHERE uuid IS NULL;
ALTER TABLE icinga_endpoint ALTER COLUMN uuid SET NOT NULL;
CREATE UNIQUE INDEX endpoint_uuid ON icinga_endpoint (uuid);

ALTER TABLE icinga_host ADD COLUMN uuid bytea UNIQUE CHECK(LENGTH(uuid) = 16);
UPDATE icinga_host SET uuid = decode(replace(gen_random_uuid()::text, '-', ''), 'hex') WHERE uuid IS NULL;
ALTER TABLE icinga_host ALTER COLUMN uuid SET NOT NULL;
CREATE UNIQUE INDEX host_uuid ON icinga_host (uuid);

ALTER TABLE icinga_service ADD COLUMN uuid bytea UNIQUE CHECK(LENGTH(uuid) = 16);
UPDATE icinga_service SET uuid = decode(replace(gen_random_uuid()::text, '-', ''), 'hex') WHERE uuid IS NULL;
ALTER TABLE icinga_service ALTER COLUMN uuid SET NOT NULL;
CREATE UNIQUE INDEX service_uuid ON icinga_service (uuid);

ALTER TABLE icinga_hostgroup ADD COLUMN uuid bytea UNIQUE CHECK(LENGTH(uuid) = 16);
UPDATE icinga_hostgroup SET uuid = decode(replace(gen_random_uuid()::text, '-', ''), 'hex') WHERE uuid IS NULL;
ALTER TABLE icinga_hostgroup ALTER COLUMN uuid SET NOT NULL;
CREATE UNIQUE INDEX hostgroup_uuid ON icinga_hostgroup (uuid);

ALTER TABLE icinga_servicegroup ADD COLUMN uuid bytea UNIQUE CHECK(LENGTH(uuid) = 16);
UPDATE icinga_servicegroup SET uuid = decode(replace(gen_random_uuid()::text, '-', ''), 'hex') WHERE uuid IS NULL;
ALTER TABLE icinga_servicegroup ALTER COLUMN uuid SET NOT NULL;
CREATE UNIQUE INDEX servicegroup_uuid ON icinga_servicegroup (uuid);

ALTER TABLE icinga_user ADD COLUMN uuid bytea UNIQUE CHECK(LENGTH(uuid) = 16);
UPDATE icinga_user SET uuid = decode(replace(gen_random_uuid()::text, '-', ''), 'hex') WHERE uuid IS NULL;
ALTER TABLE icinga_user ALTER COLUMN uuid SET NOT NULL;
CREATE UNIQUE INDEX user_uuid ON icinga_user (uuid);

ALTER TABLE icinga_usergroup ADD COLUMN uuid bytea UNIQUE CHECK(LENGTH(uuid) = 16);
UPDATE icinga_usergroup SET uuid = decode(replace(gen_random_uuid()::text, '-', ''), 'hex') WHERE uuid IS NULL;
ALTER TABLE icinga_usergroup ALTER COLUMN uuid SET NOT NULL;
CREATE UNIQUE INDEX usergroup_uuid ON icinga_usergroup (uuid);

ALTER TABLE icinga_notification ADD COLUMN uuid bytea UNIQUE CHECK(LENGTH(uuid) = 16);
UPDATE icinga_notification SET uuid = decode(replace(gen_random_uuid()::text, '-', ''), 'hex') WHERE uuid IS NULL;
ALTER TABLE icinga_notification ALTER COLUMN uuid SET NOT NULL;
CREATE UNIQUE INDEX notification_uuid ON icinga_notification (uuid);

ALTER TABLE icinga_dependency ADD COLUMN uuid bytea UNIQUE CHECK(LENGTH(uuid) = 16);
UPDATE icinga_dependency SET uuid = decode(replace(gen_random_uuid()::text, '-', ''), 'hex') WHERE uuid IS NULL;
ALTER TABLE icinga_dependency ALTER COLUMN uuid SET NOT NULL;
CREATE UNIQUE INDEX dependency_uuid ON icinga_dependency (uuid);

ALTER TABLE icinga_scheduled_downtime ADD COLUMN uuid bytea UNIQUE CHECK(LENGTH(uuid) = 16);
UPDATE icinga_scheduled_downtime SET uuid = decode(replace(gen_random_uuid()::text, '-', ''), 'hex') WHERE uuid IS NULL;
ALTER TABLE icinga_scheduled_downtime ALTER COLUMN uuid SET NOT NULL;
CREATE UNIQUE INDEX scheduled_downtime_uuid ON icinga_scheduled_downtime (uuid);

INSERT INTO director_schema_migration
(schema_version, migration_time)
VALUES (174, NOW());
