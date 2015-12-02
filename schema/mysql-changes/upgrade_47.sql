ALTER TABLE icinga_command_argument DROP INDEX sort_idx, ADD INDEX sort_idx (command_id, sort_order);
