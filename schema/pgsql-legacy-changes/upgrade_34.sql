ALTER TABLE director_generated_file ALTER COLUMN content SET DEFAULT NULL;
ALTER TABLE icinga_host_field ALTER COLUMN is_required SET NOT NULL;
ALTER TABLE icinga_service_field ALTER COLUMN is_required SET NOT NULL;

CREATE TABLE import_source (
  id serial,
  source_name character varying(64) NOT NULL,
  key_column character varying(64) NOT NULL,
  provider_class character varying(72) NOT NULL,
  PRIMARY KEY (id)
);

CREATE INDEX import_source_search_idx ON import_source (key_column);


CREATE TABLE import_source_setting (
  source_id integer NOT NULL,
  setting_name character varying(64) NOT NULL,
  setting_value text NOT NULL,
  PRIMARY KEY (source_id, setting_name),
  CONSTRAINT import_source_settings_source
  FOREIGN KEY (source_id)
    REFERENCES import_source (id)
    ON DELETE CASCADE
    ON UPDATE CASCADE
);

CREATE INDEX import_source_setting_source ON import_source_setting (source_id);


CREATE TABLE imported_rowset (
  checksum bytea CHECK(LENGTH(checksum) = 20),
  PRIMARY KEY (checksum)
);


CREATE TABLE import_run (
  id serial,
  source_id integer NOT NULL,
  rowset_checksum bytea CHECK(LENGTH(rowset_checksum) = 20),
  start_time timestamp with time zone NOT NULL,
  end_time timestamp with time zone NOT NULL,
  succeeded enum_boolean DEFAULT NULL,
  PRIMARY KEY (id),
  CONSTRAINT import_run_source
  FOREIGN KEY (source_id)
    REFERENCES import_source (id)
    ON DELETE RESTRICT
    ON UPDATE CASCADE,
  CONSTRAINT import_run_rowset
  FOREIGN KEY (rowset_checksum)
    REFERENCES imported_rowset (checksum)
    ON DELETE RESTRICT
    ON UPDATE CASCADE
);

CREATE INDEX import_run_import_source ON import_run (source_id);
CREATE INDEX import_run_rowset ON import_run (rowset_checksum);


CREATE TABLE imported_row (
  checksum bytea CHECK(LENGTH(checksum) = 20),
  object_name character varying(255) NOT NULL,
  PRIMARY KEY (checksum)
);

COMMENT ON COLUMN imported_row.checksum IS 'sha1(object_name;property_checksum;...)';


CREATE TABLE imported_rowset_row (
  rowset_checksum bytea CHECK(LENGTH(checksum) = 20),
  row_checksum bytea CHECK(LENGTH(checksum) = 20),
  PRIMARY KEY (rowset_checksum, row_checksum),
  CONSTRAINT imported_rowset_row_rowset
  FOREIGN KEY (rowset_checksum)
    REFERENCES imported_rowset (checksum)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT imported_rowset_row_row
  FOREIGN KEY (row_checksum)
    REFERENCES imported_row (checksum)
    ON DELETE RESTRICT
    ON UPDATE CASCADE
);

CREATE INDEX imported_rowset_row_rowset_checksum ON imported_rowset_row (rowset_checksum);
CREATE INDEX imported_rowset_row_row_checksum ON imported_rowset_row (row_checksum);

CREATE TABLE imported_property (
  checksum bytea CHECK(LENGTH(checksum) = 20),
  property_name character varying(64) NOT NULL,
  property_value text NOT NULL,
  format enum_property_format,
  PRIMARY KEY (checksum)
);

CREATE INDEX imported_property_search_idx ON imported_property (property_name);

CREATE TABLE imported_row_property (
  row_checksum bytea CHECK(LENGTH(row_checksum) = 20),
  property_checksum bytea CHECK(LENGTH(property_checksum) = 20),
  PRIMARY KEY (row_checksum, property_checksum),
  CONSTRAINT imported_row_property_row
  FOREIGN KEY (row_checksum)
    REFERENCES imported_row (checksum)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT imported_row_property_property
  FOREIGN KEY (property_checksum)
    REFERENCES imported_property (checksum)
    ON DELETE RESTRICT
    ON UPDATE CASCADE
);

CREATE INDEX imported_row_property_row_checksum ON imported_row_property (row_checksum);
CREATE INDEX imported_row_property_property_checksum ON imported_row_property (property_checksum);


CREATE TYPE enum_sync_rule_object_type AS ENUM('host', 'user');
CREATE TYPE enum_sync_rule_update_policy AS ENUM('merge', 'override', 'ignore');

CREATE TABLE sync_rule (
  id serial,
  rule_name character varying(255) NOT NULL,
  object_type enum_sync_rule_object_type NOT NULL,
  update_policy enum_sync_rule_update_policy NOT NULL,
  purge_existing enum_boolean NOT NULL DEFAULT 'n',
  filter_expression text DEFAULT NULL,
  PRIMARY KEY (id)
);


CREATE TYPE enum_sync_property_merge_policy AS ENUM('override', 'merge');

CREATE TABLE sync_property (
  id serial,
  rule_id integer NOT NULL,
  source_id integer NOT NULL,
  source_expression character varying(255) NOT NULL,
  destination_field character varying(64),
  priority smallint NOT NULL,
  filter_expression text DEFAULT NULL,
  merge_policy enum_sync_property_merge_policy NOT NULL,
  PRIMARY KEY (id),
  CONSTRAINT sync_property_rule
  FOREIGN KEY (rule_id)
    REFERENCES sync_rule (id)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT sync_property_source
  FOREIGN KEY (source_id)
    REFERENCES import_source (id)
    ON DELETE RESTRICT
    ON UPDATE CASCADE
);

CREATE INDEX sync_property_rule ON sync_property (rule_id);
CREATE INDEX sync_property_source ON sync_property (source_id);


CREATE TABLE import_row_modifier (
  id serial,
  property_id integer NOT NULL,
  provider_class character varying(72) NOT NULL,
  PRIMARY KEY (id)
);


CREATE TABLE import_row_modifier_setting (
  modifier_id integer NOT NULL,
  setting_name character varying(64) NOT NULL,
  setting_value text DEFAULT NULL,
  PRIMARY KEY (modifier_id)
);


CREATE TABLE director_datafield_setting (
  datafield_id integer NOT NULL,
  setting_name character varying(64) NOT NULL,
  setting_value text NOT NULL,
  PRIMARY KEY (datafield_id, setting_name),
  CONSTRAINT datafield_id_settings
  FOREIGN KEY (datafield_id)
    REFERENCES director_datafield (id)
    ON DELETE CASCADE
    ON UPDATE CASCADE
);

CREATE INDEX director_datafield_datafield ON director_datafield_setting (datafield_id);
