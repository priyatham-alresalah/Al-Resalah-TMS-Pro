-- Add login_enabled column to clients and candidates
-- Run this in Supabase SQL Editor if the columns don't exist

ALTER TABLE clients ADD COLUMN IF NOT EXISTS login_enabled boolean DEFAULT true;
ALTER TABLE candidates ADD COLUMN IF NOT EXISTS login_enabled boolean DEFAULT true;

-- Optional: Add comment for documentation
COMMENT ON COLUMN clients.login_enabled IS 'When false, client cannot log into Client Portal';
COMMENT ON COLUMN candidates.login_enabled IS 'When false, candidate cannot log into Candidate Portal';
