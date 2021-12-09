ALTER TABLE icinga_zone ADD COLUMN uuid VARBINARY(16) DEFAULT NULL AFTER id;
SET @tmp_uuid = LOWER(CONCAT(
     LPAD(HEX(FLOOR(RAND()   * 0xffff)), 4, '0'),
     LPAD(HEX(FLOOR(RAND() * 0xffff)), 4, '0'), '-',
     LPAD(HEX(FLOOR(RAND() * 0xffff)), 4, '0'), '-',
     '4',
     LPAD(HEX(FLOOR(RAND() * 0x0fff)), 3, '0'), '-',
     HEX(FLOOR(RAND() * 4 + 8)),
     LPAD(HEX(FLOOR(RAND() * 0x0fff)), 3, '0'), '-',
     LPAD(HEX(FLOOR(RAND() * 0xffff)), 4, '0'),
     LPAD(HEX(FLOOR(RAND() * 0xffff)), 4, '0'),
     LPAD(HEX(FLOOR(RAND() * 0xffff)), 4, '0')
));
UPDATE icinga_zone SET uuid = UNHEX(LPAD(LPAD(HEX(id), 8, '0'), 32, REPLACE(@tmp_uuid, '-', ''))) WHERE uuid IS NULL;
ALTER TABLE icinga_zone MODIFY COLUMN uuid VARBINARY(16) NOT NULL, ADD UNIQUE INDEX uuid (uuid);


ALTER TABLE icinga_timeperiod ADD COLUMN uuid VARBINARY(16) DEFAULT NULL AFTER id;
SET @tmp_uuid = LOWER(CONCAT(
     LPAD(HEX(FLOOR(RAND()   * 0xffff)), 4, '0'),
     LPAD(HEX(FLOOR(RAND() * 0xffff)), 4, '0'), '-',
     LPAD(HEX(FLOOR(RAND() * 0xffff)), 4, '0'), '-',
     '4',
     LPAD(HEX(FLOOR(RAND() * 0x0fff)), 3, '0'), '-',
     HEX(FLOOR(RAND() * 4 + 8)),
     LPAD(HEX(FLOOR(RAND() * 0x0fff)), 3, '0'), '-',
     LPAD(HEX(FLOOR(RAND() * 0xffff)), 4, '0'),
     LPAD(HEX(FLOOR(RAND() * 0xffff)), 4, '0'),
     LPAD(HEX(FLOOR(RAND() * 0xffff)), 4, '0')
));
UPDATE icinga_timeperiod SET uuid = UNHEX(LPAD(LPAD(HEX(id), 8, '0'), 32, REPLACE(@tmp_uuid, '-', ''))) WHERE uuid IS NULL;
ALTER TABLE icinga_timeperiod MODIFY COLUMN uuid VARBINARY(16) NOT NULL, ADD UNIQUE INDEX uuid (uuid);


ALTER TABLE icinga_command ADD COLUMN uuid VARBINARY(16) DEFAULT NULL AFTER id;
SET @tmp_uuid = LOWER(CONCAT(
     LPAD(HEX(FLOOR(RAND()   * 0xffff)), 4, '0'),
     LPAD(HEX(FLOOR(RAND() * 0xffff)), 4, '0'), '-',
     LPAD(HEX(FLOOR(RAND() * 0xffff)), 4, '0'), '-',
     '4',
     LPAD(HEX(FLOOR(RAND() * 0x0fff)), 3, '0'), '-',
     HEX(FLOOR(RAND() * 4 + 8)),
     LPAD(HEX(FLOOR(RAND() * 0x0fff)), 3, '0'), '-',
     LPAD(HEX(FLOOR(RAND() * 0xffff)), 4, '0'),
     LPAD(HEX(FLOOR(RAND() * 0xffff)), 4, '0'),
     LPAD(HEX(FLOOR(RAND() * 0xffff)), 4, '0')
));
UPDATE icinga_command SET uuid = UNHEX(LPAD(LPAD(HEX(id), 8, '0'), 32, REPLACE(@tmp_uuid, '-', ''))) WHERE uuid IS NULL;
ALTER TABLE icinga_command MODIFY COLUMN uuid VARBINARY(16) NOT NULL, ADD UNIQUE INDEX uuid (uuid);


ALTER TABLE icinga_apiuser ADD COLUMN uuid VARBINARY(16) DEFAULT NULL AFTER id;
SET @tmp_uuid = LOWER(CONCAT(
     LPAD(HEX(FLOOR(RAND()   * 0xffff)), 4, '0'),
     LPAD(HEX(FLOOR(RAND() * 0xffff)), 4, '0'), '-',
     LPAD(HEX(FLOOR(RAND() * 0xffff)), 4, '0'), '-',
     '4',
     LPAD(HEX(FLOOR(RAND() * 0x0fff)), 3, '0'), '-',
     HEX(FLOOR(RAND() * 4 + 8)),
     LPAD(HEX(FLOOR(RAND() * 0x0fff)), 3, '0'), '-',
     LPAD(HEX(FLOOR(RAND() * 0xffff)), 4, '0'),
     LPAD(HEX(FLOOR(RAND() * 0xffff)), 4, '0'),
     LPAD(HEX(FLOOR(RAND() * 0xffff)), 4, '0')
));
UPDATE icinga_apiuser SET uuid = UNHEX(LPAD(LPAD(HEX(id), 8, '0'), 32, REPLACE(@tmp_uuid, '-', ''))) WHERE uuid IS NULL;
ALTER TABLE icinga_apiuser MODIFY COLUMN uuid VARBINARY(16) NOT NULL, ADD UNIQUE INDEX uuid (uuid);


ALTER TABLE icinga_endpoint ADD COLUMN uuid VARBINARY(16) DEFAULT NULL AFTER id;
SET @tmp_uuid = LOWER(CONCAT(
     LPAD(HEX(FLOOR(RAND()   * 0xffff)), 4, '0'),
     LPAD(HEX(FLOOR(RAND() * 0xffff)), 4, '0'), '-',
     LPAD(HEX(FLOOR(RAND() * 0xffff)), 4, '0'), '-',
     '4',
     LPAD(HEX(FLOOR(RAND() * 0x0fff)), 3, '0'), '-',
     HEX(FLOOR(RAND() * 4 + 8)),
     LPAD(HEX(FLOOR(RAND() * 0x0fff)), 3, '0'), '-',
     LPAD(HEX(FLOOR(RAND() * 0xffff)), 4, '0'),
     LPAD(HEX(FLOOR(RAND() * 0xffff)), 4, '0'),
     LPAD(HEX(FLOOR(RAND() * 0xffff)), 4, '0')
));
UPDATE icinga_endpoint SET uuid = UNHEX(LPAD(LPAD(HEX(id), 8, '0'), 32, REPLACE(@tmp_uuid, '-', ''))) WHERE uuid IS NULL;
ALTER TABLE icinga_endpoint MODIFY COLUMN uuid VARBINARY(16) NOT NULL, ADD UNIQUE INDEX uuid (uuid);


ALTER TABLE icinga_host ADD COLUMN uuid VARBINARY(16) DEFAULT NULL AFTER id;
SET @tmp_uuid = LOWER(CONCAT(
     LPAD(HEX(FLOOR(RAND() * 0xffff)), 4, '0'),
     LPAD(HEX(FLOOR(RAND() * 0xffff)), 4, '0'), '-',
     LPAD(HEX(FLOOR(RAND() * 0xffff)), 4, '0'), '-',
     '4',
     LPAD(HEX(FLOOR(RAND() * 0x0fff)), 3, '0'), '-',
     HEX(FLOOR(RAND() * 4 + 8)),
     LPAD(HEX(FLOOR(RAND() * 0x0fff)), 3, '0'), '-',
     LPAD(HEX(FLOOR(RAND() * 0xffff)), 4, '0'),
     LPAD(HEX(FLOOR(RAND() * 0xffff)), 4, '0'),
     LPAD(HEX(FLOOR(RAND() * 0xffff)), 4, '0')
));
UPDATE icinga_host SET uuid = UNHEX(LPAD(LPAD(HEX(id), 8, '0'), 32, REPLACE(@tmp_uuid, '-', ''))) WHERE uuid IS NULL;
ALTER TABLE icinga_host MODIFY COLUMN uuid VARBINARY(16) NOT NULL, ADD UNIQUE INDEX uuid (uuid);


ALTER TABLE icinga_service ADD COLUMN uuid VARBINARY(16) DEFAULT NULL AFTER id;
SET @tmp_uuid = LOWER(CONCAT(
     LPAD(HEX(FLOOR(RAND()   * 0xffff)), 4, '0'),
     LPAD(HEX(FLOOR(RAND() * 0xffff)), 4, '0'), '-',
     LPAD(HEX(FLOOR(RAND() * 0xffff)), 4, '0'), '-',
     '4',
     LPAD(HEX(FLOOR(RAND() * 0x0fff)), 3, '0'), '-',
     HEX(FLOOR(RAND() * 4 + 8)),
     LPAD(HEX(FLOOR(RAND() * 0x0fff)), 3, '0'), '-',
     LPAD(HEX(FLOOR(RAND() * 0xffff)), 4, '0'),
     LPAD(HEX(FLOOR(RAND() * 0xffff)), 4, '0'),
     LPAD(HEX(FLOOR(RAND() * 0xffff)), 4, '0')
));
UPDATE icinga_service SET uuid = UNHEX(LPAD(LPAD(HEX(id), 8, '0'), 32, REPLACE(@tmp_uuid, '-', ''))) WHERE uuid IS NULL;
ALTER TABLE icinga_service MODIFY COLUMN uuid VARBINARY(16) NOT NULL, ADD UNIQUE INDEX uuid (uuid);


ALTER TABLE icinga_hostgroup ADD COLUMN uuid VARBINARY(16) DEFAULT NULL AFTER id;
SET @tmp_uuid = LOWER(CONCAT(
     LPAD(HEX(FLOOR(RAND()   * 0xffff)), 4, '0'),
     LPAD(HEX(FLOOR(RAND() * 0xffff)), 4, '0'), '-',
     LPAD(HEX(FLOOR(RAND() * 0xffff)), 4, '0'), '-',
     '4',
     LPAD(HEX(FLOOR(RAND() * 0x0fff)), 3, '0'), '-',
     HEX(FLOOR(RAND() * 4 + 8)),
     LPAD(HEX(FLOOR(RAND() * 0x0fff)), 3, '0'), '-',
     LPAD(HEX(FLOOR(RAND() * 0xffff)), 4, '0'),
     LPAD(HEX(FLOOR(RAND() * 0xffff)), 4, '0'),
     LPAD(HEX(FLOOR(RAND() * 0xffff)), 4, '0')
));
UPDATE icinga_hostgroup SET uuid = UNHEX(LPAD(LPAD(HEX(id), 8, '0'), 32, REPLACE(@tmp_uuid, '-', ''))) WHERE uuid IS NULL;
ALTER TABLE icinga_hostgroup MODIFY COLUMN uuid VARBINARY(16) NOT NULL, ADD UNIQUE INDEX uuid (uuid);


ALTER TABLE icinga_servicegroup ADD COLUMN uuid VARBINARY(16) DEFAULT NULL AFTER id;
SET @tmp_uuid = LOWER(CONCAT(
     LPAD(HEX(FLOOR(RAND()   * 0xffff)), 4, '0'),
     LPAD(HEX(FLOOR(RAND() * 0xffff)), 4, '0'), '-',
     LPAD(HEX(FLOOR(RAND() * 0xffff)), 4, '0'), '-',
     '4',
     LPAD(HEX(FLOOR(RAND() * 0x0fff)), 3, '0'), '-',
     HEX(FLOOR(RAND() * 4 + 8)),
     LPAD(HEX(FLOOR(RAND() * 0x0fff)), 3, '0'), '-',
     LPAD(HEX(FLOOR(RAND() * 0xffff)), 4, '0'),
     LPAD(HEX(FLOOR(RAND() * 0xffff)), 4, '0'),
     LPAD(HEX(FLOOR(RAND() * 0xffff)), 4, '0')
));
UPDATE icinga_servicegroup SET uuid = UNHEX(LPAD(LPAD(HEX(id), 8, '0'), 32, REPLACE(@tmp_uuid, '-', ''))) WHERE uuid IS NULL;
ALTER TABLE icinga_servicegroup MODIFY COLUMN uuid VARBINARY(16) NOT NULL, ADD UNIQUE INDEX uuid (uuid);


ALTER TABLE icinga_user ADD COLUMN uuid VARBINARY(16) DEFAULT NULL AFTER id;
SET @tmp_uuid = LOWER(CONCAT(
     LPAD(HEX(FLOOR(RAND()   * 0xffff)), 4, '0'),
     LPAD(HEX(FLOOR(RAND() * 0xffff)), 4, '0'), '-',
     LPAD(HEX(FLOOR(RAND() * 0xffff)), 4, '0'), '-',
     '4',
     LPAD(HEX(FLOOR(RAND() * 0x0fff)), 3, '0'), '-',
     HEX(FLOOR(RAND() * 4 + 8)),
     LPAD(HEX(FLOOR(RAND() * 0x0fff)), 3, '0'), '-',
     LPAD(HEX(FLOOR(RAND() * 0xffff)), 4, '0'),
     LPAD(HEX(FLOOR(RAND() * 0xffff)), 4, '0'),
     LPAD(HEX(FLOOR(RAND() * 0xffff)), 4, '0')
));
UPDATE icinga_user SET uuid = UNHEX(LPAD(LPAD(HEX(id), 8, '0'), 32, REPLACE(@tmp_uuid, '-', ''))) WHERE uuid IS NULL;
ALTER TABLE icinga_user MODIFY COLUMN uuid VARBINARY(16) NOT NULL, ADD UNIQUE INDEX uuid (uuid);


ALTER TABLE icinga_usergroup ADD COLUMN uuid VARBINARY(16) DEFAULT NULL AFTER id;
SET @tmp_uuid = LOWER(CONCAT(
     LPAD(HEX(FLOOR(RAND()   * 0xffff)), 4, '0'),
     LPAD(HEX(FLOOR(RAND() * 0xffff)), 4, '0'), '-',
     LPAD(HEX(FLOOR(RAND() * 0xffff)), 4, '0'), '-',
     '4',
     LPAD(HEX(FLOOR(RAND() * 0x0fff)), 3, '0'), '-',
     HEX(FLOOR(RAND() * 4 + 8)),
     LPAD(HEX(FLOOR(RAND() * 0x0fff)), 3, '0'), '-',
     LPAD(HEX(FLOOR(RAND() * 0xffff)), 4, '0'),
     LPAD(HEX(FLOOR(RAND() * 0xffff)), 4, '0'),
     LPAD(HEX(FLOOR(RAND() * 0xffff)), 4, '0')
));
UPDATE icinga_usergroup SET uuid = UNHEX(LPAD(LPAD(HEX(id), 8, '0'), 32, REPLACE(@tmp_uuid, '-', ''))) WHERE uuid IS NULL;
ALTER TABLE icinga_usergroup MODIFY COLUMN uuid VARBINARY(16) NOT NULL, ADD UNIQUE INDEX uuid (uuid);


ALTER TABLE icinga_notification ADD COLUMN uuid VARBINARY(16) DEFAULT NULL AFTER id;
SET @tmp_uuid = LOWER(CONCAT(
     LPAD(HEX(FLOOR(RAND()   * 0xffff)), 4, '0'),
     LPAD(HEX(FLOOR(RAND() * 0xffff)), 4, '0'), '-',
     LPAD(HEX(FLOOR(RAND() * 0xffff)), 4, '0'), '-',
     '4',
     LPAD(HEX(FLOOR(RAND() * 0x0fff)), 3, '0'), '-',
     HEX(FLOOR(RAND() * 4 + 8)),
     LPAD(HEX(FLOOR(RAND() * 0x0fff)), 3, '0'), '-',
     LPAD(HEX(FLOOR(RAND() * 0xffff)), 4, '0'),
     LPAD(HEX(FLOOR(RAND() * 0xffff)), 4, '0'),
     LPAD(HEX(FLOOR(RAND() * 0xffff)), 4, '0')
));
UPDATE icinga_notification SET uuid = UNHEX(LPAD(LPAD(HEX(id), 8, '0'), 32, REPLACE(@tmp_uuid, '-', ''))) WHERE uuid IS NULL;
ALTER TABLE icinga_notification MODIFY COLUMN uuid VARBINARY(16) NOT NULL, ADD UNIQUE INDEX uuid (uuid);


ALTER TABLE icinga_dependency ADD COLUMN uuid VARBINARY(16) DEFAULT NULL AFTER id;
SET @tmp_uuid = LOWER(CONCAT(
     LPAD(HEX(FLOOR(RAND()   * 0xffff)), 4, '0'),
     LPAD(HEX(FLOOR(RAND() * 0xffff)), 4, '0'), '-',
     LPAD(HEX(FLOOR(RAND() * 0xffff)), 4, '0'), '-',
     '4',
     LPAD(HEX(FLOOR(RAND() * 0x0fff)), 3, '0'), '-',
     HEX(FLOOR(RAND() * 4 + 8)),
     LPAD(HEX(FLOOR(RAND() * 0x0fff)), 3, '0'), '-',
     LPAD(HEX(FLOOR(RAND() * 0xffff)), 4, '0'),
     LPAD(HEX(FLOOR(RAND() * 0xffff)), 4, '0'),
     LPAD(HEX(FLOOR(RAND() * 0xffff)), 4, '0')
));
UPDATE icinga_dependency SET uuid = UNHEX(LPAD(LPAD(HEX(id), 8, '0'), 32, REPLACE(@tmp_uuid, '-', ''))) WHERE uuid IS NULL;
ALTER TABLE icinga_dependency MODIFY COLUMN uuid VARBINARY(16) NOT NULL, ADD UNIQUE INDEX uuid (uuid);


ALTER TABLE icinga_scheduled_downtime ADD COLUMN uuid VARBINARY(16) DEFAULT NULL AFTER id;
SET @tmp_uuid = LOWER(CONCAT(
     LPAD(HEX(FLOOR(RAND()   * 0xffff)), 4, '0'),
     LPAD(HEX(FLOOR(RAND() * 0xffff)), 4, '0'), '-',
     LPAD(HEX(FLOOR(RAND() * 0xffff)), 4, '0'), '-',
     '4',
     LPAD(HEX(FLOOR(RAND() * 0x0fff)), 3, '0'), '-',
     HEX(FLOOR(RAND() * 4 + 8)),
     LPAD(HEX(FLOOR(RAND() * 0x0fff)), 3, '0'), '-',
     LPAD(HEX(FLOOR(RAND() * 0xffff)), 4, '0'),
     LPAD(HEX(FLOOR(RAND() * 0xffff)), 4, '0'),
     LPAD(HEX(FLOOR(RAND() * 0xffff)), 4, '0')
));
UPDATE icinga_scheduled_downtime SET uuid = UNHEX(LPAD(LPAD(HEX(id), 8, '0'), 32, REPLACE(@tmp_uuid, '-', ''))) WHERE uuid IS NULL;
ALTER TABLE icinga_scheduled_downtime MODIFY COLUMN uuid VARBINARY(16) NOT NULL, ADD UNIQUE INDEX uuid (uuid);


INSERT INTO director_schema_migration
  (schema_version, migration_time)
  VALUES (174, NOW());
