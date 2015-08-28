ALTER TABLE icinga_host
  ADD COLUMN display_name VARCHAR(255) DEFAULT NULL,
  ADD INDEX search_idx (display_name);

