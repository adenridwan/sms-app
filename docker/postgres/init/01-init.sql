-- ===========================================
-- PostgreSQL Initialization Script
-- SMS Enterprise
-- ===========================================

-- Enable extensions
CREATE EXTENSION IF NOT EXISTS "uuid-ossp";
CREATE EXTENSION IF NOT EXISTS "pgcrypto";
CREATE EXTENSION IF NOT EXISTS "unaccent";

-- Create schemas for multi-tenant (optional)
-- Each tenant can have its own schema
-- CREATE SCHEMA IF NOT EXISTS tenant_default;

-- Set timezone
SET timezone = 'Asia/Jakarta';

-- Grant privileges
GRANT ALL PRIVILEGES ON DATABASE sms_db TO sms_user;
