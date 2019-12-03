-- SET sql_mode = 'STRICT_ALL_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION,PIPES_AS_CONCAT,ANSI_QUOTES,ERROR_FOR_DIVISION_BY_ZERO';

CREATE TABLE icinga_assign_filter (
  filter_checksum VARBINARY(20) NOT NULL,-- sha1(target_type:filter_format:filter_string)
  target_type ENUM('host', 'service') NOT NULL,
  filter_format ENUM('legacy', 'json') NOT NULL,
  filter_string TEXT NOT NULL,
  PRIMARY KEY (filter_checksum)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE icinga_assign_filter_matching_host (
  filter_checksum VARBINARY(20) NOT NULL,
  host_id INT(10) UNSIGNED NOT NULL,
  PRIMARY KEY (host_id, filter_checksum),
  INDEX filter_search_idx(filter_checksum, host_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

INSERT INTO icinga_assign_filter SELECT
  UNHEX(SHA1(cf.apply_to || ':legacy:' || cf.assign_filter)) AS filter_checksum,
  cf.apply_to,
  'legacy' as filter_format,
  cf.assign_filter
  FROM (
     SELECT 'host' AS apply_to, assign_filter
         FROM icinga_service
        WHERE assign_filter IS NOT NULL
        GROUP BY assign_filter
     UNION SELECT 'host' AS apply_to, assign_filter
         FROM icinga_service_set
        WHERE assign_filter IS NOT NULL
        GROUP BY assign_filter
     UNION SELECT 'host' AS apply_to, assign_filter
         FROM icinga_hostgroup
        WHERE assign_filter IS NOT NULL
        GROUP BY assign_filter
      UNION SELECT 'service'AS apply_to, assign_filter
         FROM icinga_servicegroup
        WHERE assign_filter IS NOT NULL
        GROUP BY assign_filter
      UNION SELECT apply_to, assign_filter
         FROM icinga_notification
        WHERE assign_filter IS NOT NULL
        GROUP BY assign_filter
      UNION SELECT apply_to, assign_filter
         FROM icinga_dependency
        WHERE assign_filter IS NOT NULL
        GROUP BY assign_filter
      UNION SELECT apply_to, assign_filter
         FROM icinga_scheduled_downtime
        WHERE assign_filter IS NOT NULL
        GROUP BY assign_filter
  ) cf;

ALTER TABLE icinga_service
  ADD COLUMN assign_filter_checksum VARBINARY(20) DEFAULT NULL AFTER assign_filter;
-- noinspection SqlWithoutWhere
UPDATE icinga_service SET assign_filter_checksum = UNHEX(SHA1('host:legacy:' || assign_filter));
ALTER TABLE icinga_service DROP COLUMN assign_filter;

ALTER TABLE icinga_service_set
  ADD COLUMN assign_filter_checksum VARBINARY(20) DEFAULT NULL AFTER assign_filter;
-- noinspection SqlWithoutWhere
UPDATE icinga_service_set SET assign_filter_checksum = UNHEX(SHA1('host:legacy:' || assign_filter));
ALTER TABLE icinga_service_set DROP COLUMN assign_filter;

ALTER TABLE icinga_hostgroup
  ADD COLUMN assign_filter_checksum VARBINARY(20) DEFAULT NULL AFTER assign_filter;
-- noinspection SqlWithoutWhere
UPDATE icinga_hostgroup SET assign_filter_checksum = UNHEX(SHA1('host:legacy:' || assign_filter));
ALTER TABLE icinga_hostgroup DROP COLUMN assign_filter;

ALTER TABLE icinga_servicegroup
  ADD COLUMN assign_filter_checksum VARBINARY(20) DEFAULT NULL AFTER assign_filter;
-- noinspection SqlWithoutWhere
UPDATE icinga_servicegroup SET assign_filter_checksum = UNHEX(SHA1('service:legacy:' || assign_filter));
ALTER TABLE icinga_servicegroup DROP COLUMN assign_filter;

ALTER TABLE icinga_notification
  ADD COLUMN assign_filter_checksum VARBINARY(20) DEFAULT NULL AFTER assign_filter;
-- noinspection SqlWithoutWhere
UPDATE icinga_notification SET assign_filter_checksum = UNHEX(SHA1(apply_to || ':legacy:' || assign_filter));
ALTER TABLE icinga_notification DROP COLUMN assign_filter, DROP COLUMN apply_to;

ALTER TABLE icinga_dependency
  ADD COLUMN assign_filter_checksum VARBINARY(20) DEFAULT NULL AFTER assign_filter;
-- noinspection SqlWithoutWhere
UPDATE icinga_dependency SET assign_filter_checksum = UNHEX(SHA1(apply_to || ':legacy:' || assign_filter));
ALTER TABLE icinga_dependency DROP COLUMN assign_filter;

ALTER TABLE icinga_scheduled_downtime
  ADD COLUMN assign_filter_checksum VARBINARY(20) DEFAULT NULL AFTER assign_filter;
-- noinspection SqlWithoutWhere
UPDATE icinga_scheduled_downtime SET assign_filter_checksum = UNHEX(SHA1(apply_to || ':legacy:' || assign_filter));
ALTER TABLE icinga_scheduled_downtime DROP COLUMN assign_filter, DROP COLUMN apply_to;

INSERT INTO director_schema_migration
  (schema_version, migration_time)
  VALUES (169, NOW());
