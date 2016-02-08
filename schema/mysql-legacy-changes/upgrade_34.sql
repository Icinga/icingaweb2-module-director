ALTER TABLE icinga_host_field MODIFY is_required ENUM('y', 'n') NOT NULL;
ALTER TABLE icinga_service_field MODIFY is_required ENUM('y', 'n') NOT NULL;
