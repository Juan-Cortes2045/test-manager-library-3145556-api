# test-manager-library-3145556-api

> **Prueba técnica · Ficha ADSO 3145556** — entrega: martes 13 de octubre de 2026, 17:00 (hora Colombia).

Backend de *Manager Library* en **PHP 8.3 con Laravel 13**. Implementa el spec de
[`test-manager-library-3145556-docs`](../test-manager-library-3145556-docs) según su traducción en `docs/spec-laravel/`
(plan, interpretaciones y tareas). El modelo de datos y el contrato son los del spec original; no se amplían.

## Qué hay hoy (paso 4: T-01 y T-02)

| Elemento | Archivo | Regla |
|---|---|---|
| `GET /api/v1/health` → `{"status":"ok"}` | `routes/api.php`, `app/Adapters/Http/Controllers/HealthController.php` | T-01; `api-contract.md` §3.1 |
| Prefijo `/api/v1`; sin rutas web, sin `/up`, sin rutas `storage/*` | `bootstrap/app.php`, `config/filesystems.php` | `api-contract.md` §1 |
| Cuerpo de error único `{code, message, details}` para toda excepción | `app/Adapters/Http/ErrorResponder.php` | RNF-4; `architecture.md` §5 |
| Migración inicial: esquema `library`, seis tablas, PK, FK, `CHECK`, índices y las cinco categorías | `database/migrations/2026_10_13_000000_create_library_schema.php` | T-02; `data-model.md` §3, §4, §5, §9 |
| Consultas de comprobación del modelo | `database/checks/data-model-section-10.sql` | `data-model.md` §10 |
| Punto único de composición (aún sin enlaces) | `app/Bootstrap/LibraryServiceProvider.php` | Artículo III |
| Capas hexagonales vacías | `app/Domain`, `app/Application/{Ports,Services}`, `app/Adapters/{Persistence,Storage,Security}` | Artículos I y II |
| Imagen Docker: ejecuta las migraciones y después sirve en el puerto 8000 | `Dockerfile`, `docker/entrypoint.sh` | Artículo V; I-08 |

## Cambios respecto al esqueleto por defecto de Laravel

| Por defecto | Problema con el spec | Cambio |
|---|---|---|
| Migraciones `users`, `cache`, `jobs` | Solo seis tablas (`data-model.md` §2) | Eliminadas, junto con `User`, `UserFactory` y `DatabaseSeeder` |
| Sesión, caché y colas con driver `database` | Necesitan tablas propias de Laravel | `array`, `array` y `sync`; trabajos fallidos `null` |
| Tabla `migrations` en el esquema por defecto | Sería una séptima tabla en `library` | Se guarda en `public.migrations`; `search_path` = `library,public` |
| Rutas `web`, `/up` y `storage/{path}` | Fuera del contrato | Eliminadas o desactivadas |
| Errores 422 `{message, errors}` y páginas HTML de error | Cuerpo de error único | Todo pasa por `ErrorResponder` |
| `timestamps()` | Sin `created_at`/`updated_at` | La migración no los usa; ninguna columna lleva `DEFAULT` |

`ck_book_total_positive` y `ck_user_role` **no** se crean: `data-model.md` §4 los marca como pendientes (T-18).

## Arranque

Dentro de `infra` (recomendado): ver `../test-manager-library-3145556-infra/README.md`.

## Verificación

```bash
# Pruebas (sin base de datos): health y cuerpo de error
docker run --rm -v "$PWD":/app -w /app php:8.3-cli php vendor/bin/phpunit

# Rutas expuestas: solo GET api/v1/health
docker run --rm -v "$PWD":/app -w /app php:8.3-cli php artisan route:list

# Modelo de datos, con la API levantada desde infra
docker compose -f ../test-manager-library-3145556-infra/docker-compose.yml exec -T db \
  psql -U library -d library -f - < database/checks/data-model-section-10.sql
```

## Pendiente

Todo lo demás del núcleo (T-03 a T-17): dominio, casos de uso, autenticación JWT, admin inicial, resto de rutas. El estado real
de cada tarea está en `docs/spec-laravel/tasks.md`.
