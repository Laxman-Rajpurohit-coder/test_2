---
description: Critical knowledge for Railway CLI and deployments
---

# Railway Deployment & Debugging Quirks

When debugging or deploying to Railway for this specific workspace, remember the following critical lessons learned from past debugging sessions:

## 1. Railway CLI vs Local `.env` Override Bug
When using `railway run php artisan [command]`, the Railway CLI merges the remote environment variables with the local `.env` file. **The local `.env` takes precedence.**

Because the local `.env` typically has `DB_CONNECTION=sqlite`, running a command like `railway run php artisan tinker` will actually connect to the **LOCAL SQLite database**, NOT the Railway production Postgres database. This can lead to extreme confusion when checking if tables exist in production.

**Solution:** 
When you need to run an artisan command against the live production Postgres database using Railway CLI, always explicitly pass the correct database connection variable, or bypass the local `.env` temporarily.
*Example (Windows CMD):*
`cmd /c "set DB_CONNECTION=pgsql&& railway run php artisan tinker"`

## 2. Model Namespaces in Controller Closures
When using Closures for Laravel validation rules (e.g., in `TenantInviteController`), if you reference an Eloquent model (like `TenantInvite`), it MUST be fully qualified (e.g., `\App\Models\TenantInvite`), otherwise Laravel will throw a `Class not found` fatal error (500) during the request. 

## 3. Database Table Availability During Deployments
If a 500 error occurs shortly after a deployment triggers (e.g., `Undefined table: relation does not exist`), it is usually because the new code is actively receiving traffic before the `releaseCommand` (`php artisan migrate --force`) has finished executing. 
Do not panic and assume the migration failed. Check `railway status` to confirm if the build/deploy is actually finished.
