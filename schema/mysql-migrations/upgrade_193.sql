ALTER TABLE icinga_hostgroup_host
  ADD COLUMN operator ENUM('=', '+=', '-=') NOT NULL DEFAULT '=';

ALTER TABLE icinga_servicegroup_service
  ADD COLUMN operator ENUM('=', '+=', '-=') NOT NULL DEFAULT '=';

ALTER TABLE icinga_usergroup_user
  ADD COLUMN operator ENUM('=', '+=', '-=') NOT NULL DEFAULT '=';

ALTER TABLE branched_icinga_host
  ADD COLUMN groupsadd TEXT DEFAULT NULL,
  ADD COLUMN groupsremove TEXT DEFAULT NULL;

ALTER TABLE branched_icinga_service
  ADD COLUMN groupsadd TEXT DEFAULT NULL,
  ADD COLUMN groupsremove TEXT DEFAULT NULL;

ALTER TABLE branched_icinga_user
  ADD COLUMN groupsadd TEXT DEFAULT NULL,
  ADD COLUMN groupsremove TEXT DEFAULT NULL;

INSERT INTO director_schema_migration
  (schema_version, migration_time)
  VALUES (193, NOW());
