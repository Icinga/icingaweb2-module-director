DROP TABLE branched_icinga_host;
DROP TABLE branched_icinga_hostgroup;
DROP TABLE branched_icinga_servicegroup;
DROP TABLE branched_icinga_usergroup;
DROP TABLE branched_icinga_user;
DROP TABLE branched_icinga_zone;
DROP TABLE branched_icinga_timeperiod;
DROP TABLE branched_icinga_command;
DROP TABLE branched_icinga_apiuser;
DROP TABLE branched_icinga_endpoint;
DROP TABLE branched_icinga_service;
DROP TABLE branched_icinga_service_set;
DROP TABLE branched_icinga_notification;
DROP TABLE branched_icinga_scheduled_downtime;
DROP TABLE branched_icinga_dependency;
DROP TABLE director_branch_activity;
DROP TABLE director_branch;

INSERT INTO director_schema_migration
  (schema_version, migration_time)
  VALUES (193, NOW());
