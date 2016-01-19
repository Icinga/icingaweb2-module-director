ALTER TABLE icinga_host DROP KEY object_name, ADD UNIQUE KEY object_name (object_name);

