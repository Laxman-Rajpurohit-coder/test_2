-- -------------------------------------------------------------------------------------
-- SAFEGUARD SCRIPT: Check for duplicate (tenant_id, customer_number) pairs
-- -------------------------------------------------------------------------------------
-- Run this script BEFORE deploying the migration:
-- 2026_07_30_083311_fix_conversations_unique_constraint
--
-- If this query returns ANY rows, those duplicates must be resolved (merged or deleted)
-- before running `php artisan migrate`, otherwise PostgreSQL will throw a unique 
-- constraint violation and fail the deployment.
-- -------------------------------------------------------------------------------------

SELECT 
    tenant_id, 
    customer_number, 
    COUNT(*) as duplicate_count,
    array_agg(id) as duplicate_conversation_ids
FROM 
    conversations
GROUP BY 
    tenant_id, 
    customer_number
HAVING 
    COUNT(*) > 1
ORDER BY 
    duplicate_count DESC;
