ALTER TABLE director_deployment_log
  MODIFY COLUMN startup_log TEXT DEFAULT NULL,
  ADD COLUMN stage_name VARCHAR(64) DEFAULT NULL AFTER duration_dump,
  ADD COLUMN stage_collected ENUM('y', 'n') DEFAULT NULL AFTER stage_name;

