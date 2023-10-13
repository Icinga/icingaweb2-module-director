ALTER TABLE director_datafield ADD COLUMN uuid VARBINARY(16) DEFAULT NULL AFTER id;
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
UPDATE director_datafield SET uuid = UNHEX(LPAD(LPAD(HEX(id), 8, '0'), 32, REPLACE(@tmp_uuid, '-', ''))) WHERE uuid IS NULL;
ALTER TABLE director_datafield MODIFY COLUMN uuid VARBINARY(16) NOT NULL, ADD UNIQUE INDEX uuid (uuid);

ALTER TABLE director_datalist ADD COLUMN uuid VARBINARY(16) DEFAULT NULL AFTER id;
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
UPDATE director_datalist SET uuid = UNHEX(LPAD(LPAD(HEX(id), 8, '0'), 32, REPLACE(@tmp_uuid, '-', ''))) WHERE uuid IS NULL;
ALTER TABLE director_datalist MODIFY COLUMN uuid VARBINARY(16) NOT NULL, ADD UNIQUE INDEX uuid (uuid);

INSERT INTO director_schema_migration
  (schema_version, migration_time)
  VALUES (186, NOW());
