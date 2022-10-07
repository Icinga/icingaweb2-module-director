DELETE sr.*
  FROM sync_run sr
  JOIN sync_rule s ON s.id = sr.rule_id
  WHERE sr.last_former_activity = sr.last_related_activity
    AND s.object_type != 'datalistEntry' AND sr.start_time > '2022-09-21 00:00:00';

DELETE FROM sync_run
  WHERE (objects_created + objects_deleted + objects_modified) = 0;

INSERT INTO director_schema_migration
  (schema_version, migration_time)
  VALUES (182, NOW());
