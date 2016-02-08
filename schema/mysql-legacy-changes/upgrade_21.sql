DROP TABLE director_datalist_value;

CREATE TABLE director_datalist_entry (
  list_id INT(10) UNSIGNED NOT NULL,
  entry_name VARCHAR(255) DEFAULT NULL,
  entry_value TEXT DEFAULT NULL,
  format enum ('string', 'expression', 'json'),
  PRIMARY KEY (list_id, entry_name),
  CONSTRAINT director_datalist_value_datalist
    FOREIGN KEY datalist (list_id)
    REFERENCES director_datalist (id)
    ON DELETE CASCADE
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

