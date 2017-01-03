SET @stmt = (SELECT IF(
    (SELECT EXISTS(
        SELECT * FROM information_schema.table_constraints
        WHERE
            table_schema   = DATABASE()
            AND table_name = 'icinga_service_set'
            AND constraint_name = 'icinga_service_set_host'
    )),
    'ALTER TABLE icinga_service_set DROP FOREIGN KEY icinga_service_set_host',
    'SELECT 1'
));

PREPARE stmt FROM @stmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
SET @stmt = NULL;


SET @stmt = (SELECT IF(
    (SELECT EXISTS(
        SELECT * FROM information_schema.table_constraints
        WHERE
            table_schema   = DATABASE()
            AND table_name = 'icinga_service_set'
            AND constraint_name = 'icinga_service_set_ibfk_1'
    )),
    'ALTER TABLE icinga_service_set DROP FOREIGN KEY icinga_service_set_ibfk_1',
    'SELECT 1'
));

PREPARE stmt FROM @stmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
SET @stmt = NULL;


SET @stmt = (SELECT IF(
    (SELECT EXISTS(
        SELECT * FROM information_schema.table_constraints
        WHERE
            table_schema   = DATABASE()
            AND table_name = 'icinga_service_set'
            AND constraint_name = 'icinga_service_set_ibfk_2'
    )),
    'ALTER TABLE icinga_service_set DROP FOREIGN KEY icinga_service_set_ibfk_2',
    'SELECT 1'
));

PREPARE stmt FROM @stmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
SET @stmt = NULL;



SET @stmt = (SELECT IF(
    (SELECT EXISTS(
        SELECT * FROM information_schema.table_constraints
        WHERE
            table_schema   = DATABASE()
            AND table_name = 'icinga_service_set'
            AND constraint_name = 'icinga_service_set_ibfk_3'
    )),
    'ALTER TABLE icinga_service_set DROP FOREIGN KEY icinga_service_set_ibfk_3',
    'SELECT 1'
));

PREPARE stmt FROM @stmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
SET @stmt = NULL;



SET @stmt = (SELECT IF(
    (SELECT EXISTS(
        SELECT * FROM information_schema.table_constraints
        WHERE
            table_schema   = DATABASE()
            AND table_name = 'icinga_service'
            AND constraint_name = 'icinga_service_service_set'
    )),
    'ALTER TABLE icinga_service DROP FOREIGN KEY icinga_service_service_set',
    'SELECT 1'
));

PREPARE stmt FROM @stmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
SET @stmt = NULL;


SET @stmt = (SELECT IF(
    (SELECT EXISTS(
        SELECT * FROM information_schema.table_constraints
        WHERE
            table_schema   = DATABASE()
            AND table_name = 'icinga_service'
            AND constraint_name = 'icinga_service_ibfk_1'
    )),
    'ALTER TABLE icinga_service DROP FOREIGN KEY icinga_service_ibfk_1',
    'SELECT 1'
));

PREPARE stmt FROM @stmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
SET @stmt = NULL;


SET @stmt = (SELECT IF(
    (SELECT EXISTS(
        SELECT 1
        FROM information_schema.statistics
        WHERE table_schema = SCHEMA()
              AND table_name = 'icinga_service'
              AND index_name = 'icinga_service_service_set'
    )),
    'ALTER TABLE icinga_service DROP INDEX icinga_service_service_set',
    'SELECT 1'
));

PREPARE stmt FROM @stmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
SET @stmt = NULL;


SET @stmt = (SELECT IF(
    (SELECT EXISTS(
        SELECT 1
        FROM information_schema.statistics
        WHERE table_schema = SCHEMA()
              AND table_name = 'icinga_service_set'
              AND index_name = 'icinga_service_set_ibfk_1'
    )),
    'ALTER TABLE icinga_service_set DROP INDEX icinga_service_set_ibfk_1',
    'SELECT 1'
));

PREPARE stmt FROM @stmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
SET @stmt = NULL;


SET @stmt = (SELECT IF(
    (SELECT EXISTS(
        SELECT 1
        FROM information_schema.statistics
        WHERE table_schema = SCHEMA()
              AND table_name = 'icinga_service_set'
              AND index_name = 'icinga_service_set_host'
    )),
    'ALTER TABLE icinga_service_set DROP INDEX icinga_service_set_host',
    'SELECT 1'
));

PREPARE stmt FROM @stmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
SET @stmt = NULL;


SET @stmt = (SELECT IF(
    (SELECT EXISTS(
        SELECT 1
        FROM information_schema.statistics
        WHERE table_schema = SCHEMA()
            AND table_name = 'icinga_service'
            AND index_name = 'icinga_service_ibfk_1'
    )),
    'ALTER TABLE icinga_service_set DROP INDEX icinga_service_ibfk_1',
    'SELECT 1'
));

PREPARE stmt FROM @stmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
SET @stmt = NULL;


SET @stmt = (SELECT IF(
    (SELECT EXISTS(
        SELECT 1
        FROM information_schema.statistics
        WHERE table_schema = SCHEMA()
              AND table_name = 'icinga_service_set'
              AND index_name = 'icinga_service_set_ibfk_2'
    )),
    'ALTER TABLE icinga_service_set DROP INDEX icinga_service_set_ibfk_2',
    'SELECT 1'
));

PREPARE stmt FROM @stmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
SET @stmt = NULL;


ALTER TABLE icinga_service_set
  ADD CONSTRAINT icinga_service_set_host
    FOREIGN KEY host (host_id)
    REFERENCES icinga_host (id)
    ON DELETE CASCADE
    ON UPDATE CASCADE;

ALTER TABLE icinga_service
  ADD CONSTRAINT icinga_service_service_set
    FOREIGN KEY service_set (service_set_id)
    REFERENCES icinga_service_set (id)
    ON DELETE CASCADE
    ON UPDATE CASCADE;

INSERT INTO director_schema_migration
  (schema_version, migration_time)
  VALUES (126, NOW());
