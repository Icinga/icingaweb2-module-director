ALTER TABLE import_run DROP FOREIGN KEY import_run_rowset;
ALTER TABLE import_run CHANGE imported_rowset_checksum rowset_checksum varbinary(20) DEFAULT NULL;
ALTER TABLE import_run ADD CONSTRAINT import_run_rowset FOREIGN KEY rowset (rowset_checksum) REFERENCES imported_rowset (checksum) ON DELETE RESTRICT ON UPDATE CASCADE;

