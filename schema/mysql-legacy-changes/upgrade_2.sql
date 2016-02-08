ALTER TABLE icinga_command_argument ADD UNIQUE KEY unique_idx (command_id, argument_name);
