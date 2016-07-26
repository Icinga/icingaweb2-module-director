UPDATE director_deployment_log SET startup_log = LEFT(startup_log, 20480) || '

[..] shortened '
|| (LENGTH(startup_log) - 40960)
|| ' bytes by Director on schema upgrade [..]

' || RIGHT(startup_log, 20480) WHERE LENGTH(startup_log) > 61440;

OPTIMIZE TABLE director_deployment_log;

INSERT INTO director_schema_migration
  (schema_version, migration_time)
  VALUES (102, NOW());
