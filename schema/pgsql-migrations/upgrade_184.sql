ALTER TABLE branched_icinga_notification
    ADD COLUMN users_var character varying(255) DEFAULT NULL;

ALTER TABLE branched_icinga_notification
    ADD COLUMN user_groups_var character varying(255) DEFAULT NULL;

INSERT INTO director_schema_migration
  (schema_version, migration_time)
  VALUES (184, NOW());
