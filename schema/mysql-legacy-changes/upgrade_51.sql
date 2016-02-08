ALTER TABLE sync_rule MODIFY object_type enum('host', 'host_template', 'service', 'service_template', 'command', 'command_template', 'user', 'user_template', 'hostgroup', 'servicegroup', 'usergroup', 'datalistEntry', 'endpoint', 'zone') NOT NULL;

