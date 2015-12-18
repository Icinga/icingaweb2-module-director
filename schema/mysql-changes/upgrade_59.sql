ALTER TABLE icinga_service
  ADD COLUMN use_agent ENUM('y', 'n') NOT NULL DEFAULT 'n';

