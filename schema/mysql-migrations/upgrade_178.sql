ALTER TABLE director_activity_log
    ADD COLUMN live_modification ENUM('scheduled', 'succeeded', 'failed', 'impossible', 'disabled') NOT NULL;

UPDATE director_activity_log SET live_modification = 'disabled';

CREATE TABLE icinga_modified_attribute (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT NOT NULL,
    activity_id BIGINT(20) UNSIGNED DEFAULT NULL,
    state ENUM('scheduled_for_reset', 'scheduled', 'applied') NOT NULL,
    action ENUM('create', 'delete', 'modify') NOT NULL,
    icinga_object_type VARCHAR(64) NOT NULL,
    icinga_object_name VARCHAR(255) NOT NULL,
    modification MEDIUMTEXT NOT NULL,
    ts_scheduled BIGINT(20) NOT NULL,
    ts_applied BIGINT(20) DEFAULT NULL,
    PRIMARY KEY (id),
    FOREIGN KEY activity_log_id (activity_id)
       REFERENCES director_activity_log (id)
       ON DELETE RESTRICT
       ON UPDATE CASCADE,
    INDEX sort_idx (ts_scheduled)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

INSERT INTO director_schema_migration
(schema_version, migration_time)
VALUES (178, NOW());
