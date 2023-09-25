SELECT count(*) as count
FROM information_schema.COLUMNS
WHERE
  TABLE_SCHEMA = '%s'
  AND TABLE_NAME = '%s'
  AND COLUMN_NAME = '%s'


