DELETE FROM sync_run AS sr
  WHERE EXISTS (
    SELECT 1 FROM sync_rule AS s
      WHERE s.id = sr.rule_id
        AND s.object_type != 'datalistEntry'
        AND sr.start_time > '2022-09-21 00:00:00'
  ) AND sr.last_former_activity = sr.last_related_activity;

DELETE FROM sync_run
  WHERE (objects_created + objects_deleted + objects_modified) = 0;

INSERT INTO director_schema_migration
  (schema_version, migration_time)
  VALUES (182, NOW());
