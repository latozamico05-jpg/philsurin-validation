-- Run schema.sql first. This migration only adds new structures and does not delete legacy data.
SOURCE database/schema.sql;
SOURCE database/seed.sql;
ALTER TABLE users MODIFY role VARCHAR(50) NOT NULL DEFAULT 'viewer';
-- Review and map legacy grading_records into masterlist/validation_sessions after backup.
