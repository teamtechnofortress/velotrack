# Laravel integration files for VeloTrack Phase 7

These files must be merged into an existing Laravel application. **This directory is not a complete Laravel installation.** See the repository root `README.md` for setup, security and endpoint details.

1. Install Laravel with compatible PHP/Composer on a machine with the right environment; deploy appropriately on Hostinger.
2. Install Sanctum and enable API routes. Ensure `User` uses `HasApiTokens`.
3. Merge all Phase 5, 6 and 7 API controllers, models, migrations and routes into the real backend.
4. Configure server-only `.env`, run migrations and verify HTTPS, document root and PHP permissions.
5. Run: `php artisan test --filter=FleetAccessTest` once the actual Laravel installation and test database are ready.

Important: subscription endpoint reports Free and unverified; team collaboration is a staging integration facility, not a paid service. Do not connect live billing without purchase verification and authorization gates.
