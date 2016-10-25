ALTER TABLE icinga_service ADD COLUMN assign_filter text DEFAULT NULL;

WITH flat_assign AS (

    SELECT 
        service_id,
          CASE WHEN COUNT(*) = 0 THEN NULL
               WHEN COUNT(*) = 1 THEN MAX(sa.filter_string)
               ELSE ARRAY_TO_STRING(ARRAY_AGG(sa.filter_string), '&') END AS filter_string
    FROM (
        SELECT
          sa_not.service_id,
          CASE WHEN COUNT(*) = 0 THEN NULL
               WHEN COUNT(*) = 1 THEN MAX(sa_not.filter_string)
               ELSE '(' || ARRAY_TO_STRING(ARRAY_AGG(sa_not.filter_string), '&') || ')' END AS filter_string
          FROM ( SELECT
            sa.service_id,
            '!' || sa.filter_string AS filter_string
            FROM icinga_service_assignment sa
            WHERE assign_type = 'ignore'
          ) sa_not
          GROUP BY service_id

        UNION ALL

        SELECT
          sa_yes.service_id,
          CASE WHEN COUNT(*) = 0 THEN NULL
               WHEN COUNT(*) = 1 THEN MAX(sa_yes.filter_string)
               ELSE '(' || ARRAY_TO_STRING(ARRAY_AGG(sa_yes.filter_string), '|') || ')' END AS filter_string
          FROM ( SELECT
            sa.service_id,
            sa.filter_string AS filter_string
            FROM icinga_service_assignment sa
            WHERE assign_type = 'assign'
          ) sa_yes
          GROUP BY service_id

    ) sa GROUP BY service_id

) UPDATE icinga_service s
  SET assign_filter = flat_assign.filter_string
  FROM flat_assign
  WHERE s.id = flat_assign.service_id;

DROP TABLE icinga_service_assignment;

ALTER TABLE icinga_service_set ADD COLUMN assign_filter text DEFAULT NULL;

WITH flat_assign AS (

    SELECT
        service_set_id,
          CASE WHEN COUNT(*) = 0 THEN NULL
               WHEN COUNT(*) = 1 THEN MAX(sa.filter_string)
               ELSE ARRAY_TO_STRING(ARRAY_AGG(sa.filter_string), '&') END AS filter_string
    FROM (
        SELECT
          sa_not.service_set_id,
          CASE WHEN COUNT(*) = 0 THEN NULL
               WHEN COUNT(*) = 1 THEN MAX(sa_not.filter_string)
               ELSE '(' || ARRAY_TO_STRING(ARRAY_AGG(sa_not.filter_string), '&') || ')' END AS filter_string
          FROM ( SELECT
            sa.service_set_id,
            '!' || sa.filter_string AS filter_string
            FROM icinga_service_set_assignment sa
            WHERE assign_type = 'ignore'
          ) sa_not
          GROUP BY service_set_id

        UNION ALL

        SELECT
          sa_yes.service_set_id,
          CASE WHEN COUNT(*) = 0 THEN NULL
               WHEN COUNT(*) = 1 THEN MAX(sa_yes.filter_string)
               ELSE '(' || ARRAY_TO_STRING(ARRAY_AGG(sa_yes.filter_string), '|') || ')' END AS filter_string
          FROM ( SELECT
            sa.service_set_id,
            sa.filter_string AS filter_string
            FROM icinga_service_set_assignment sa
            WHERE assign_type = 'assign'
          ) sa_yes
          GROUP BY service_set_id

    ) sa GROUP BY service_set_id

) UPDATE icinga_service_set s
  SET assign_filter = flat_assign.filter_string
  FROM flat_assign
  WHERE s.id = flat_assign.service_set_id;

DROP TABLE icinga_service_set_assignment;


ALTER TABLE icinga_notification ADD COLUMN assign_filter text DEFAULT NULL;

WITH flat_assign AS (

    SELECT
        notification_id,
          CASE WHEN COUNT(*) = 0 THEN NULL
               WHEN COUNT(*) = 1 THEN MAX(sa.filter_string)
               ELSE ARRAY_TO_STRING(ARRAY_AGG(sa.filter_string), '&') END AS filter_string
    FROM (
        SELECT
          sa_not.notification_id,
          CASE WHEN COUNT(*) = 0 THEN NULL
               WHEN COUNT(*) = 1 THEN MAX(sa_not.filter_string)
               ELSE '(' || ARRAY_TO_STRING(ARRAY_AGG(sa_not.filter_string), '&') || ')' END AS filter_string
          FROM ( SELECT
            sa.notification_id,
            '!' || sa.filter_string AS filter_string
            FROM icinga_notification_assignment sa
            WHERE assign_type = 'ignore'
          ) sa_not
          GROUP BY notification_id

        UNION ALL

        SELECT
          sa_yes.notification_id,
          CASE WHEN COUNT(*) = 0 THEN NULL
               WHEN COUNT(*) = 1 THEN MAX(sa_yes.filter_string)
               ELSE '(' || ARRAY_TO_STRING(ARRAY_AGG(sa_yes.filter_string), '|') || ')' END AS filter_string
          FROM ( SELECT
            sa.notification_id,
            sa.filter_string AS filter_string
            FROM icinga_notification_assignment sa
            WHERE assign_type = 'assign'
          ) sa_yes
          GROUP BY notification_id

    ) sa GROUP BY notification_id

) UPDATE icinga_notification s
  SET assign_filter = flat_assign.filter_string
  FROM flat_assign
  WHERE s.id = flat_assign.notification_id;


DROP TABLE icinga_notification_assignment;

ALTER TABLE icinga_hostgroup ADD COLUMN assign_filter text DEFAULT NULL;

WITH flat_assign AS (

    SELECT
        hostgroup_id,
          CASE WHEN COUNT(*) = 0 THEN NULL
               WHEN COUNT(*) = 1 THEN MAX(sa.filter_string)
               ELSE ARRAY_TO_STRING(ARRAY_AGG(sa.filter_string), '&') END AS filter_string
    FROM (
        SELECT
          sa_not.hostgroup_id,
          CASE WHEN COUNT(*) = 0 THEN NULL
               WHEN COUNT(*) = 1 THEN MAX(sa_not.filter_string)
               ELSE '(' || ARRAY_TO_STRING(ARRAY_AGG(sa_not.filter_string), '&') || ')' END AS filter_string
          FROM ( SELECT
            sa.hostgroup_id,
            '!' || sa.filter_string AS filter_string
            FROM icinga_hostgroup_assignment sa
            WHERE assign_type = 'ignore'
          ) sa_not
          GROUP BY hostgroup_id

        UNION ALL

        SELECT
          sa_yes.hostgroup_id,
          CASE WHEN COUNT(*) = 0 THEN NULL
               WHEN COUNT(*) = 1 THEN MAX(sa_yes.filter_string)
               ELSE '(' || ARRAY_TO_STRING(ARRAY_AGG(sa_yes.filter_string), '|') || ')' END AS filter_string
          FROM ( SELECT
            sa.hostgroup_id,
            sa.filter_string AS filter_string
            FROM icinga_hostgroup_assignment sa
            WHERE assign_type = 'assign'
          ) sa_yes
          GROUP BY hostgroup_id

    ) sa GROUP BY hostgroup_id

) UPDATE icinga_hostgroup s
  SET assign_filter = flat_assign.filter_string
  FROM flat_assign
  WHERE s.id = flat_assign.hostgroup_id;


DROP TABLE icinga_hostgroup_assignment;


ALTER TABLE icinga_servicegroup ADD COLUMN assign_filter text DEFAULT NULL;


DROP TYPE enum_assign_type;


INSERT INTO director_schema_migration
  (schema_version, migration_time)
  VALUES (120, NOW());
