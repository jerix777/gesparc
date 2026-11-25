# GesParc

Laravel 13 / PHP 8.3, Blade sans build front. Interface et messages en français.

- Toute donnée métier utilise le trait `BelongsToInstitution` (cloisonnement par institution) : ne jamais le contourner
  avec `withoutGlobalScopes()` dans un contrôleur sans vérifier l'institution à la main.
- Tout changement de statut ou d'affectation d'un équipement passe par `App\Services\EquipmentLifecycle`.
- Droits : Gates dans `AppServiceProvider`, documentées dans `docs/ARCHITECTURE.md` (à tenir à jour).
- Tests : `php artisan test` (SQLite en mémoire).
