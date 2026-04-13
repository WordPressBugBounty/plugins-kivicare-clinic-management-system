# AGENTS.md

## Purpose
- This plugin is a WordPress + React hybrid: PHP boots features and REST routes; React powers the dashboard UI.
- Start from `kivicare-clinic-management-system.php` and `app/baseClasses/KCApp.php` to understand lifecycle and hooks.

## Architecture You Need First
- **Plugin bootstrap**: `kivicare-clinic-management-system.php` defines constants, runs activation/migrations, and initializes `KCApp`.
- **App wiring**: `app/baseClasses/KCApp.php` registers admin menu, REST API, shortcodes, blocks, Elementor widgets, cron hooks, and auth filters.
- **REST API composition**: `app/controllers/KCRestAPI.php` + `app/baseClasses/KCModuleRegistry.php` register module/controller pairs, then call `registerRoutes()` on `rest_api_init`.
- **Controller base pattern**: controllers extend `app/baseClasses/KCBaseController.php`; routes are auto-prefixed to `kivicare/v1` and often module-prefixed.
- **Dashboard delivery**: `app/admin/KCDashboardPermalinkHandler.php` handles rewrite rules and enqueues React entry `app/dashboard/main.jsx` via Vite manifest.
- **Frontend API flow**: `app/dashboard/api/apiClient.js` uses Axios, injects `X-WP-Nonce`, updates nonce from responses, and sends `X-KC-View-Path`.

## Data + Domain Patterns (Project-Specific)
- Models extend `app/baseClasses/KCBaseModel.php` with schema-driven camelCase property mapping to DB snake_case columns.
- Querying uses `app/baseClasses/KCQueryBuilder.php` (`Model::query()`, `Model::table('alias')`, custom joins/selects).
- Query cache is built into model/query builder; prefer targeted invalidation methods (`flushCacheForId`, `patchCache`) over global flush.
- Timezone handling is intentional: appointments persist UTC columns from local inputs in `app/models/KCAppointment.php`; slot generation uses doctor timezone in `app/services/KCTimeSlotService.php`.

## Extension Boundaries
- Register extra REST modules/controllers via `do_action('kivicare_register_modules', $registry)`.
- Override controller classes/routes/permissions through filters in `KCModuleRegistry` and `KCBaseController` (e.g. `kivicare_controller_{module/controller}`).
- Payment gateways are factory-registered in `app/baseClasses/KCPaymentGatewayFactory.php` and extensible via `kc_payment_gateways`.
- Telemed providers are factory-registered in `app/baseClasses/KCTelemedFactory.php` via `kc_telemed_providers`.
- Dashboard routes are modular in `app/dashboard/router/routeRegistry.jsx` and filterable through `wp.hooks.applyFilters('kivicare.dashboard.routes', ...)`.

## Roles, Permissions, and Routing
- KiviCare role names are prefixed (`KIVI_CARE_PREFIX`) and defined via helpers in `app/baseClasses/KCBase.php`.
- Capabilities are centralized in `app/baseClasses/KCPermissions.php`; many UI/API checks depend on capability keys (not just WP roles).
- Dashboard slugs/rewrite access are role-aware; see `app/admin/KCDashboardPermalinkHandler.php` before changing route behavior.

## Developer Workflows (Verified from repo files)
- Install PHP deps: `composer install`
- Install JS deps: `npm install` (or `bun install`)
- Dev server: `npm run dev`
- Build assets: `npm run build`
- Lint frontend: `npm run lint`
- Preview built assets: `npm run preview`
- Run migrations (WP-CLI): `wp kc migrate` (registered in `app/database/classes/KCMigrator.php`)
- Packaging script: `npm run bundle` runs `bundle.sh` (generates POT via `wp i18n make-pot`, builds, strips dev files, zips plugin)

## High-Value Gotchas
- Do not register REST routes outside `rest_api_init`; `KCBaseController::registerRoute()` enforces this.
- If you add a React entrypoint, also add it in `vite.config.js` `v4wp({ input: ... })` and enqueue it from PHP.
- Shortcodes should extend `app/abstracts/KCShortcodeAbstract.php` to inherit Vite asset loading + `kc_frontend` localization.
- Activation/version changes trigger migrations (`KCActivate::activate()`); schema changes should be migration-first under `app/database/migrations/`.

## Related Docs Worth Checking
- `docs/dashboard-routes.md` (frontend routing conventions)
- `docs/REFACTORING_SUMMARY.md` (service-vs-controller separation example)
- `docs/webhook_developer_guide.md` and `docs/custom-notification-api.md` (integration-heavy modules)

