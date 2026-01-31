-- ============================================================
-- CLEAR ALL DATA - Run in Supabase SQL Editor
-- ============================================================
-- WARNING: This permanently deletes ALL application data.
-- Auth users (auth.users) are NOT touched - you can still log in
-- after running, but profiles will be empty (run Sync Users to restore).
-- If a table does not exist, that line will error - comment it out.
-- ============================================================

TRUNCATE TABLE
  training_checkpoints,
  training_candidates,
  certificates,
  payments,
  invoices,
  quotations,
  client_orders,
  trainings,
  inquiries,
  candidates,
  clients,
  training_master,
  profiles
RESTART IDENTITY CASCADE;

-- Clear auth users (Supabase Auth)
-- Run this last - you will need to create new users or sign up again
DELETE FROM auth.users;
