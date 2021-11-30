ALTER TABLE icinga_service_set ADD COLUMN uuid VARBINARY(16) DEFAULT NULL AFTER id;
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
UPDATE icinga_service_set SET uuid = UNHEX(LPAD(LPAD(HEX(id), 8, '0'), 32, REPLACE(@tmp_uuid, '-', ''))) WHERE uuid IS NULL;
ALTER TABLE icinga_service_set MODIFY COLUMN uuid VARBINARY(16) NOT NULL, ADD UNIQUE INDEX uuid (uuid);


INSERT INTO director_schema_migration
  (schema_version, migration_time)
  VALUES ('177', NOW());
