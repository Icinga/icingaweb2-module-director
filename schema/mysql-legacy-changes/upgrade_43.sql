CREATE TABLE icinga_service_assignment (
  id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  service_id INT(10) UNSIGNED NOT NULL,
  filter_string TEXT NOT NULL,  
  PRIMARY KEY (id),
  CONSTRAINT icinga_service_assignment
    FOREIGN KEY service (service_id)
    REFERENCES icinga_service (id)
    ON DELETE CASCADE
    ON UPDATE CASCADE
) ENGINE=InnoDB;


