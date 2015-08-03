ALTER TABLE icinga_command_argument DROP COLUMN value_format, ADD COLUMN argument_format enum_property_format NOT NULL DEFAULT 'string';

