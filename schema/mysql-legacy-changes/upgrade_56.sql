ALTER TABLE director_generated_file
  ADD COLUMN cnt_object INT(10) UNSIGNED NOT NULL DEFAULT 0,
  ADD COLUMN cnt_template INT(10) UNSIGNED NOT NULL DEFAULT 0;

UPDATE director_generated_file
SET cnt_object = ROUND(
  (LENGTH(content) - LENGTH( REPLACE(content, 'object ', '') ) )
  / LENGTH('object ')
), cnt_template = ROUND(
  (LENGTH(content) - LENGTH( REPLACE(content, 'template ', '') ) )
  / LENGTH('template ')
);

