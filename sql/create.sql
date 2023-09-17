CREATE TABLE IF NOT EXISTS %s (
    id mediumint(8) unsigned not null auto_increment,
    form_id mediumint(8) unsigned not null,
    is_active tinyint(1) not null default 1,
    meta longtext,              PRIMARY KEY  (id),
    KEY form_id (form_id)
    ) %s;