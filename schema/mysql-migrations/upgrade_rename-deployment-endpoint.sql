UPDATE director_deployment_log dl 
  JOIN icinga_endpoint e ON dl.peer_identity = e.host
   SET dl.peer_identity = e.object_name
 WHERE dl.peer_identity != e.object_name;

INSERT INTO director_schema_migration
  (schema_version, migration_time)
  VALUES (XXX, NOW());
