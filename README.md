# Task Manager API

API REST para la gestión de tareas, desarrollada con **Symfony** y **API Platform**. Se ejecuta completamente en Docker.

## Stack

- PHP 8.4 (FPM) + Symfony + API Platform
- PostgreSQL 16
- Nginx 1.27
- Mailpit (captura de correos en desarrollo)
- Autenticación JWT (LexikJWTAuthenticationBundle)

## Requisitos

- Docker
- Docker Compose

## Puesta en marcha

```bash
git clone https://github.com/Georkis/task task-manager
cd task-manager
docker compose up -d --build
```

Al iniciar, el contenedor `php` ejecuta automáticamente (`startup.sh`):

1. Instala las dependencias de Composer si no existen.
2. Espera a que la base de datos esté disponible y la crea si hace falta.
3. Genera el par de claves JWT (si no existen).
4. Aplica las migraciones de Doctrine.
5. Carga datos de demostración (fixtures) la primera vez, si `LOAD_FIXTURES=1`.
6. Calienta la caché.

Para seguir el arranque:

```bash
docker compose logs -f php
```

## Servicios y puertos

| Servicio  | URL / Puerto            | Descripción                          |
|-----------|-------------------------|--------------------------------------|
| API       | http://localhost:8081   | Aplicación (Nginx)                   |
| Docs API  | http://localhost:8081/api | Documentación Swagger/OpenAPI      |
| PostgreSQL| `localhost:5433`        | Base de datos (`app` / `app` / `app`) |
| Mailpit   | http://localhost:8025   | Bandeja de correos (SMTP en `1025`)  |

## Variables de entorno

Se pueden definir en un fichero `.env` junto al `docker-compose.yml`:

| Variable        | Por defecto | Descripción                                   |
|-----------------|-------------|-----------------------------------------------|
| `APP_ENV`       | `dev`       | Entorno de Symfony                            |
| `APP_DEBUG`     | `1`         | Modo debug                                    |
| `LOAD_FIXTURES` | `1`         | Carga datos de demo en el primer arranque     |

## Uso de la API REST

La documentación interactiva (Swagger UI) está en **http://localhost:8081/api**. Desde ahí se pueden probar todos los endpoints.

### Formatos

API Platform soporta por defecto:

- `application/ld+json` (JSON-LD, por defecto)
- `application/json`
- `application/hal+json`

Indica el formato con la cabecera `Accept` y envía el cuerpo con `Content-Type`.

### Autenticación (JWT)

1. Obtener un token:

```bash
curl -X POST http://localhost:8081/api/login_check \
  -H "Content-Type: application/json" \
  -d '{"email": "usuario@example.com", "password": "password"}'
```

Respuesta:

```json
{ "token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9..." }
```

2. Usar el token en las peticiones siguientes:

```bash
curl http://localhost:8081/api/tasks \
  -H "Authorization: Bearer <TOKEN>" \
  -H "Accept: application/json"
```

En Swagger UI, pulsa **Authorize** e introduce `Bearer <TOKEN>`.

### Endpoints disponibles

#### Login Check

| Método | Ruta               | Acción                    |
|--------|--------------------|---------------------------|
| `POST` | `/api/login_check` | Crear un token de usuario |

#### Task

Requiere autenticación (`ROLE_WORKER` como mínimo). Crear, reemplazar, actualizar y eliminar tareas es solo para `ROLE_ADMIN`.

| Método   | Ruta                       | Acción                                   | Rol / permiso |
|----------|----------------------------|------------------------------------------|---------------|
| `GET`    | `/api/tasks`               | Listar tareas (paginado)                 | `ROLE_WORKER` |
| `POST`   | `/api/tasks`               | Crear una tarea                          | `ROLE_ADMIN`  |
| `GET`    | `/api/tasks/{id}`          | Obtener una tarea                        | `TASK_VIEW`   |
| `PUT`    | `/api/tasks/{id}`          | Reemplazar una tarea                     | `ROLE_ADMIN`  |
| `PATCH`  | `/api/tasks/{id}`          | Actualizar parcialmente (`Content-Type: application/merge-patch+json`) | `ROLE_ADMIN` |
| `DELETE` | `/api/tasks/{id}`          | Eliminar una tarea                       | `ROLE_ADMIN`  |
| `POST`   | `/api/tasks/{id}/status`   | Cambiar el estado de una tarea           | `TASK_UPDATE_STATUS` |
| `POST`   | `/api/tasks/{id}/validate` | Validar una tarea                        | `TASK_VALIDATE` |
| `POST`   | `/api/tasks/{id}/reject`   | Rechazar una tarea                       | `TASK_REJECT` |

Campos de una tarea:

| Campo             | Escritura | Descripción                                              |
|-------------------|-----------|----------------------------------------------------------|
| `title`           | Sí        | Obligatorio, máx. 160 caracteres                         |
| `description`     | Sí        | Opcional, máx. 5000 caracteres                           |
| `startAt`         | Sí        | Fecha de inicio (obligatoria)                            |
| `endAt`           | Sí        | Fecha de fin (obligatoria, posterior a `startAt`)        |
| `assignedTo`      | Sí        | IRI del usuario asignado (debe tener `ROLE_WORKER`)      |
| `id`, `status`, `createdBy`, `reviewedBy`, `reviewedAt`, `rejectionReason`, `createdAt`, `updatedAt` | No | Solo lectura; el estado inicial es `Pending` |

#### User

Todas las operaciones requieren `ROLE_ADMIN`.

| Método   | Ruta               | Acción                          |
|----------|--------------------|---------------------------------|
| `GET`    | `/api/users`       | Listar usuarios                 |
| `POST`   | `/api/users`       | Crear un usuario                |
| `GET`    | `/api/users/{id}`  | Obtener un usuario              |
| `PATCH`  | `/api/users/{id}`  | Actualizar un usuario (`Content-Type: application/merge-patch+json`) |
| `DELETE` | `/api/users/{id}`  | Eliminar un usuario             |

Campos de un usuario:

| Campo      | Escritura | Descripción                                                  |
|------------|-----------|--------------------------------------------------------------|
| `email`    | Sí        | Obligatorio, único (se usa para iniciar sesión)              |
| `name`     | Sí        | Obligatorio, máx. 120 caracteres                             |
| `roles`    | Sí        | `ROLE_ADMIN` y/o `ROLE_WORKER` (por defecto `ROLE_WORKER`)   |
| `password` | Sí        | Mínimo 8 caracteres; no se devuelve en las respuestas        |
| `id`, `createdAt` | No | Solo lectura                                                 |

### Ejemplos

Crear un usuario (como administrador):

```bash
curl -X POST http://localhost:8081/api/users \
  -H "Authorization: Bearer <TOKEN>" \
  -H "Content-Type: application/json" \
  -d '{"email": "worker@example.com", "name": "Worker", "roles": ["ROLE_WORKER"], "password": "12345678"}'
```

Crear una tarea (como administrador) asignada al usuario con id `2`:

```bash
curl -X POST http://localhost:8081/api/tasks \
  -H "Authorization: Bearer <TOKEN>" \
  -H "Content-Type: application/json" \
  -d '{
    "title": "Mi primera tarea",
    "description": "Descripción de ejemplo",
    "startAt": "2026-11-23T09:00:00+00:00",
    "endAt": "2026-11-23T17:00:00+00:00",
    "assignedTo": "/api/users/2"
  }'
```

### Paginación

Las colecciones (`/api/tasks`, `/api/users`) están paginadas por defecto por API Platform:

```
GET /api/tasks?page=2
```

> Actualmente no hay filtros ni ordenación configurados en los recursos.

## Comandos útiles

```bash
# Consola de Symfony
docker compose exec php php bin/console <comando>

# Crear y aplicar migraciones
docker compose exec php php bin/console make:migration
docker compose exec php php bin/console doctrine:migrations:migrate

# Recargar fixtures
docker compose exec php php bin/console doctrine:fixtures:load

# Ver rutas de la API
docker compose exec php php bin/console debug:router | grep api

# Tests
docker compose exec php php bin/phpunit

# Acceder a la base de datos
docker compose exec database psql -U app -d app
```

## Gestión de los contenedores

```bash
docker compose up -d         # Levantar
docker compose down          # Detener
docker compose down -v       # Detener y borrar la base de datos (volumen db_data)
docker compose ps            # Estado
```

## Pruebas de correo

Los correos enviados por la aplicación se capturan en Mailpit: http://localhost:8025.

Configuración SMTP para la aplicación: host `mailpit`, puerto `1025`, sin autenticación.

## Estructura relevante

```
.
├── docker/
│   ├── nginx/default.conf   # Configuración de Nginx
│   └── php/
│       ├── Dockerfile       # Imagen PHP 8.4 FPM
│       └── startup.sh       # Script de arranque
├── docker-compose.yml
├── config/jwt/              # Claves JWT (generadas automáticamente)
└── src/                     # Código de la aplicación
```

## Solución de problemas

- **El puerto 8081, 5433, 8025 o 1025 está ocupado:** cambia el mapeo de puertos en `docker-compose.yml`.
- **La API no responde al inicio:** el contenedor `php` espera a la base de datos y aplica migraciones; revisa `docker compose logs -f php`.
- **Error de permisos en `var/`:** `docker compose exec -u root php chown -R docker:docker var config/jwt`.
- **Reiniciar desde cero:** `docker compose down -v && docker compose up -d --build`.
