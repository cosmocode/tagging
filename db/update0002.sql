--- add language column (work around alter table not working with primary key)

DROP INDEX idx_pid;

CREATE TABLE taggings_tmp (pid, tag, tagger, PRIMARY KEY(pid, tag, tagger));
INSERT INTO taggings_tmp SELECT pid, tag, tagger FROM taggings;
DROP TABLE taggings;


CREATE TABLE taggings (pid, tag, tagger, lang, PRIMARY KEY(pid, tag, tagger));
INSERT INTO taggings SELECT pid, tag, tagger, '' FROM taggings_tmp;
DROP TABLE taggings_tmp;

CREATE INDEX idx_taggings_pid ON taggings(pid);
CREATE INDEX idx_taggings_lang ON taggings(lang);