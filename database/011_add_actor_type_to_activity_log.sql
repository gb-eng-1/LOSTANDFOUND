-- Add actor_type column to activity_log table
ALTER TABLE activity_log ADD COLUMN actor_type VARCHAR(50) DEFAULT 'admin' AFTER action;
