SELECT s.id, s.is_active, s.form_id, s.meta, f.title as form_title
FROM (
         SELECT *
         FROM %s
             LIMIT %s,%s
     ) s
         INNER JOIN %s f
ON s.form_id = f.id
ORDER BY s.id DESC