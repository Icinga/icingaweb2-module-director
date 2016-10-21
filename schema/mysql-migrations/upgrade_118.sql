ALTER TABLE icinga_service ADD COLUMN assign_filter TEXT;

UPDATE icinga_service s JOIN (

    SELECT 
        service_id,
          CASE WHEN COUNT(*) = 0 THEN NULL
               WHEN COUNT(*) = 1 THEN sa.filter_string
               ELSE GROUP_CONCAT(sa.filter_string SEPARATOR '&') END AS filter_string
    FROM (
        SELECT
          sa_not.service_id,
          CASE WHEN COUNT(*) = 0 THEN NULL
               WHEN COUNT(*) = 1 THEN sa_not.filter_string
               ELSE '(' || GROUP_CONCAT(sa_not.filter_string SEPARATOR '&') || ')' END AS filter_string
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
               WHEN COUNT(*) = 1 THEN sa_yes.filter_string
               ELSE '(' || GROUP_CONCAT(sa_yes.filter_string SEPARATOR '|') || ')' END AS filter_string
          FROM ( SELECT
            sa.service_id,
            sa.filter_string AS filter_string
            FROM icinga_service_assignment sa
            WHERE assign_type = 'assign'
          ) sa_yes
          GROUP BY service_id

    ) sa GROUP BY service_id

) flat_assign ON s.id = flat_assign.service_id SET s.assign_filter = flat_assign.filter_string;

DROP TABLE icigna_service_assignment;

INSERT INTO director_schema_migration
  (schema_version, migration_time)
  VALUES (118, NOW());
