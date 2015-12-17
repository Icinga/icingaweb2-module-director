ALTER TABLE icinga_host
  ADD COLUMN has_agent ENUM('y', 'n') DEFAULT NULL,
  ADD COLUMN master_should_connect ENUM('y', 'n') DEFAULT NULL,
  ADD COLUMN accept_config ENUM('y', 'n') DEFAULT NULL;

