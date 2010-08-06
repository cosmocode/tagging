CREATE TABLE taggings (pid, tag, tagger, PRIMARY KEY(pid, tag, tagger));
CREATE INDEX idx_pid ON taggings(pid);
