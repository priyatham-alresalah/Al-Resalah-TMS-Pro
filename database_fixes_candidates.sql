-- ============================================
-- FIX: Add missing columns to candidates table
-- ============================================
-- 
-- The candidates table is missing:
-- 1. client_id - to link candidates to companies (optional)
-- 2. created_by - to track who created the candidate (required)
--
-- Run these ALTER TABLE statements in your Supabase SQL Editor
-- ============================================

-- Add client_id column (nullable, foreign key to clients table)
ALTER TABLE public.candidates
ADD COLUMN IF NOT EXISTS client_id uuid;

-- Add foreign key constraint for client_id
ALTER TABLE public.candidates
ADD CONSTRAINT candidates_client_id_fkey 
FOREIGN KEY (client_id) REFERENCES public.clients(id)
ON DELETE SET NULL;

-- Add created_by column (nullable, foreign key to auth.users)
ALTER TABLE public.candidates
ADD COLUMN IF NOT EXISTS created_by uuid;

-- Add foreign key constraint for created_by
ALTER TABLE public.candidates
ADD CONSTRAINT candidates_created_by_fkey 
FOREIGN KEY (created_by) REFERENCES auth.users(id)
ON DELETE SET NULL;

-- Optional: Add index on client_id for better query performance
CREATE INDEX IF NOT EXISTS idx_candidates_client_id ON public.candidates(client_id);

-- Optional: Add index on created_by for better query performance
CREATE INDEX IF NOT EXISTS idx_candidates_created_by ON public.candidates(created_by);

-- ============================================
-- VERIFICATION QUERIES
-- ============================================
-- Run these to verify the columns were added:
--
-- SELECT column_name, data_type, is_nullable 
-- FROM information_schema.columns 
-- WHERE table_schema = 'public' 
--   AND table_name = 'candidates'
-- ORDER BY ordinal_position;
--
-- ============================================
