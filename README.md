<p align="center"><h1>Timeboard FREE API</h1></p>

## RoomController

El `RoomController` gestiona la administración de salas (rooms) en la API. Permite listar, crear, consultar, actualizar y eliminar salas usando una conexión dinámica a la base de datos.

### Endpoints y funcionalidades

-   **GET /rooms**

    -   Lista todas las salas existentes.
    -   Parámetro opcional: `db_connection` para seleccionar la base de datos.
    -   Respuesta: JSON con el listado de salas.

-   **POST /rooms**

    -   Crea una nueva sala.
    -   Requiere en el body:
        -   `name` (string, requerido, único)
        -   `status` (entero, opcional)
    -   Valida que el nombre no se repita.
    -   Respuesta: JSON con el ID de la sala creada y mensaje de éxito.

-   **GET /rooms/{id}**

    -   Consulta los datos de una sala específica por su ID.
    -   Respuesta: JSON con los datos de la sala o error si no existe.

-   **PUT /rooms/{id}**

    -   Actualiza los datos de una sala existente.
    -   Permite modificar `name` (único) y `status`.
    -   Respuesta: Mensaje de éxito o error si no existe o no hay cambios.

-   **DELETE /rooms/{id}**
    -   Elimina una sala por su ID.
    -   Respuesta: Mensaje de éxito o error si no existe.

### Validaciones

-   El campo `name` es obligatorio y único.
-   El campo `status` es opcional y debe ser un entero.

### Ejemplo de uso (JSON)

```json
POST /rooms
{
  "name": "Sala 1",
  "status": 1,
  "db_connection": "nombre_conexion"
}
```

### Notas

-   Todos los métodos usan la conexión dinámica recibida en el parámetro `db_connection`.
-   Las respuestas de error incluyen el mensaje de excepción si ocurre algún problema en la base de datos.

## ChatController

El `ChatController` permite gestionar el historial de mensajes de chat entre clientes y especialistas, así como las fuentes/métodos de chat (WhatsApp, email, SMS, etc.). Utiliza la conexión dinámica recibida en `db_connection`.

### Endpoints y funcionalidades

-   **GET /chat**

    -   Lista todos los mensajes de chat registrados.
    -   Parámetro opcional: `db_connection`.
    -   Respuesta: JSON con el listado de mensajes.

-   **POST /chat**

    -   Crea un nuevo mensaje de chat.
    -   Requiere en el body:
        -   `client_id` (entero, requerido)
        -   `method_id` (entero, requerido, referencia a chat_sources)
        -   `subject` (string, opcional)
        -   `specialist_id` (entero, requerido)
        -   `content` (string, requerido)
        -   `status` (string, opcional)
    -   Respuesta: JSON con el ID del mensaje creado y mensaje de éxito.

-   **GET /chat/{id}**

    -   Consulta los datos de un mensaje específico por su ID.
    -   Respuesta: JSON con los datos del mensaje o error si no existe.

-   **PUT /chat/{id}**

    -   Actualiza los datos de un mensaje existente.
    -   Permite modificar `subject`, `content` y `status`.
    -   Respuesta: Mensaje de éxito o error si no existe o no hay cambios.

-   **DELETE /chat/{id}**

    -   Elimina un mensaje por su ID.
    -   Respuesta: Mensaje de éxito o error si no existe.

-   **GET /chat/sources**
    -   Lista todos los métodos/fuentes de chat disponibles (WhatsApp, email, etc.).
    -   Respuesta: JSON con el listado de fuentes.

### Tablas relacionadas

-   **chat_sources**: Métodos/fuentes de chat (WhatsApp, email, SMS, etc.)
-   **chat_log**: Historial de mensajes de chat

### Ejemplo de uso (JSON)

```json
POST /chat
{
  "client_id": 1,
  "method_id": 2,
  "subject": "Consulta",
  "specialist_id": 5,
  "content": "Hola, ¿cómo puedo reservar?",
  "status": "sent",
  "db_connection": "nombre_conexion"
}
```

### Notas

-   Todos los métodos usan la conexión dinámica recibida en el parámetro `db_connection`.
-   Las respuestas de error incluyen el mensaje de excepción si ocurre algún problema en la base de datos.

## ChatSourceController

El `ChatSourceController` permite gestionar las fuentes o métodos de chat (WhatsApp, email, SMS, etc.) de forma dinámica.

### Endpoints y funcionalidades

-   **GET /chat-sources**

    -   Lista todas las fuentes de chat registradas.
    -   Parámetro opcional: `db_connection`.
    -   Respuesta: JSON con el listado de fuentes.

-   **POST /chat-sources**

    -   Crea una nueva fuente de chat.
    -   Requiere en el body:
        -   `name` (string, requerido, único)
        -   `token` (json, opcional)
    -   Respuesta: JSON con el ID de la fuente creada y mensaje de éxito.

-   **GET /chat-sources/{id}**

    -   Consulta los datos de una fuente específica por su ID.
    -   Respuesta: JSON con los datos de la fuente o error si no existe.

-   **PUT /chat-sources/{id}**

    -   Actualiza los datos de una fuente existente.
    -   Permite modificar `name` y `token`.
    -   Respuesta: Mensaje de éxito o error si no existe o no hay cambios.

-   **DELETE /chat-sources/{id}**
    -   Elimina una fuente por su ID.
    -   Respuesta: Mensaje de éxito o error si no existe.

### Ejemplo de uso (JSON)

```json
POST /chat-sources
{
  "name": "WhatsApp",
  "token": {"api_key": "123456"},
  "db_connection": "nombre_conexion"
}
```

### Notas

-   Todos los métodos usan la conexión dinámica recibida en el parámetro `db_connection`.
-   Las respuestas de error incluyen el mensaje de excepción si ocurre algún problema en la base de datos.

## History

El `HistoryController` gestiona el historial de la aplicación (tabla `history`). Permite operaciones CRUD sobre registros históricos.

### Endpoints y funcionalidades

-   **GET /history**
    -   Lista todos los registros de historial.
    -   Parámetro `db_connection` obligatorio (en header o body).
    -   Respuesta: 200 OK con array de objetos históricos.
-   **POST /history**
    -   Crea un nuevo registro histórico.
    -   Body (JSON):
        ```json
        {
            "type": "S",             // S, O, A, P, SIMPLE, FILE
            "title": "Título",
            "description": "Descripción opcional",
            "created_by": 5,           // ID de usuario que crea
            "file": <archivo opcional si type="FILE">
        }
        ```
    -   Respuesta: 201 Created con el objeto creado.
-   **GET /history/{id}**
    -   Obtiene un registro histórico por su ID.
    -   Respuesta: 200 OK con el objeto o 404 Not Found.
-   **PUT /history/{id}**
    -   Actualiza campos de un registro histórico existente.
    -   Body: cualquiera de los campos `type`, `title`, `description`, `created_by`, o `file` (cuando `type` = FILE).
    -   Respuesta: 200 OK con el objeto actualizado o 404 Not Found.
-   **DELETE /history/{id}**
    -   Elimina un registro histórico por ID. Si tiene `type = FILE`, borra también el archivo asociado.
    -   Respuesta: 200 OK con mensaje de éxito o 404 Not Found.

### Ejemplo curl

```bash
curl -X GET "http://localhost:8000/api/history?db_connection=company_db" \
    -H "Authorization: Bearer <token>"
```

## Endpoint: POST /setroom

Permite asignar el `room_id` a un usuario que puede estar en `users` o en `users_temp`.

URL: /api/setroom

Headers:

-   Authorization: Bearer <token>
-   Content-Type: application/json

Body (JSON):

{
"id_user": 123,
"user_type": "user", // one of: user, invitation, fake
"room": 2
}

Validaciones:

-   `id_user`: entero, requerido
-   `user_type`: requerido; valores permitidos: `user`, `invitation`, `fake`
-   `room`: entero, requerido

Comportamiento:

-   Si `user_type` es `invitation`, actualiza la tabla `users_temp`.
-   Para `user` o `fake`, actualiza la tabla `users`.
-   Responde 200 OK con { "message": "Room actualizado correctamente" } en éxito.
-   Responde 404 si no se encuentra el usuario o no se hicieron cambios.
-   Responde 500 en caso de error del servidor y registra el error en logs.

Ejemplo curl:

```bash
curl -X POST 'http://localhost:8000/api/setroom' \
    -H 'Authorization: Bearer <token>' \
    -H 'Content-Type: application/json' \
    -H 'db_connection: <tu_conn>' \
    -d '{"id_user":123, "user_type":"user", "room":2}'
```

Ejemplo PowerShell:

```powershell
$body = @{ id_user = 123; user_type = 'user'; room = 2 } | ConvertTo-Json
Invoke-RestMethod -Method Post -Uri 'http://localhost:8000/api/setroom' -Body $body -ContentType 'application/json' -Headers @{'Authorization'='Bearer <token>'; 'db_connection' = '<tu_conn>'}
```

Notas:

-   El endpoint usa la conexión dinámica `db_connection` presente en headers o middleware. Asegúrate de enviarla.
-   Si quieres que el endpoint intente actualizar en `users` y, si no existe, en `users_temp`, puedo cambiar el comportamiento.

## Automatic mode (automatic_mode)

Breve: cuando `settings`.`automatic_mode` está en `true`, un proceso programado marcará automáticamente como `checkout` (status = 3) los appointments cuya `end_date` ya haya pasado.

Cómo funciona:

-   Se creó un comando Artisan `app:appointments:auto` que recorre todas las compañías, configura la conexión dinámica a la base de datos de cada compañía y, si en `settings` el registro `automatic_mode` está activo, actualiza los appointments con `end_date <= now()` que no estén ya en `checkout` ni `cancelados`.

Cómo ejecutar (cron):

-   En proyectos Laravel 11 ya existe `app/Console/Kernel.php` centralizado; no es necesario crear uno nuevo. Registra el comando en el Kernel existente dentro del método `schedule()` así:

```php
// app/Console/Kernel.php (Laravel 11)
protected function schedule(Schedule $schedule): void
{
    $schedule->command('app:appointments:auto')->everyMinute()->withoutOverlapping();
}
```

-   Luego use system cron para invocar el scheduler de Laravel cada minuto:

```sh
* * * * * cd /path/to/project && php artisan schedule:run >> /dev/null 2>&1
```

-   En Windows puede usar el Programador de tareas para ejecutar cada minuto el comando equivalente:

```powershell
cd C:\path\to\project; php artisan schedule:run
```

Notas:

-   El comando configura la conexión dinámica para cada compañía usando la tabla `servers` (sqlite) y `companies.db_name`.
-   Si desea que el proceso haga otras transiciones (por ejemplo marcar `in_room` al inicio), puedo ajustar la lógica.

## AppointmentStatusController

Este controlador permite cambiar el estado de una cita de **checked_in** a **in_room**, actualizando el campo `date_in_room`.

### Endpoint: POST /changeStatus

-   URL: `/api/changeStatus`
-   Headers:
    -   `Authorization: Bearer <token>`
    -   `Content-Type: application/json`
-   Body:

-   Responses:
    -   200 OK:
        ```json
        {
            "message": "Estado actualizado a in_room",
            "appointment": {
                /* datos de la cita actualizada */
            }
        }
        ```
    -   400 Bad Request: falta `db_connection`
    -   404 Not Found: cita no encontrada
    -   422 Unprocessable Entity: cita no está en estado `checked_in`
    -   500 Internal Error: error al actualizar

````markdown
# Uso

```bash
curl -X POST https://tu-dominio/api/changeStatus \
  -H "Authorization: Bearer <token>" \
  -H "Content-Type: application/json" \
  -d '{"db_connection":"company_db","appointment_id":45}'
```

# Notas

-   Se requiere la conexión dinámica a la base de datos en `db_connection`.

## Historial Personalizado

Este módulo permite definir campos adicionales para el historial de clientes y citas, asociarlos a categorías y almacenar sus valores.

### Definiciones de Campos

-   **GET /historial/fields-definitions**: Listar todas las definiciones.
-   **POST /historial/fields-definitions**: Crear nueva definición.
    -   Body: `in_appointments` (boolean), `in_client` (boolean), `field_name`, `field_type`, `options?`, `required_for_appointment?`.
-   **GET /historial/fields-definitions/{id}**: Ver definición.
-   **PUT /historial/fields-definitions/{id}**: Actualizar definición.
-   **DELETE /historial/fields-definitions/{id}**: Eliminar definición.

### Asociaciones en Historial

-   **GET /historial/fields-in-history**: Listar asociaciones.
-   **POST /historial/fields-in-history**: Asociar campo a categoría.
    -   Body: `history_category_id`, `custom_field_definition_id`, `required_for_history?`.
-   **DELETE /historial/fields-in-history/{historyCategoryId}/{fieldDefId}**: Desasociar.

### Valores de Campos

-   **GET /historial/fields-values**: Listar todos los valores.
-   **POST /historial/fields-values**: Crear valor.
    -   Body: `history_id?`, `appointment_id?`, `client_id?`, `field_id`, `field_value`.
-   **GET /historial/fields-values/{id}**: Ver valor.
-   **PUT /historial/fields-values/{id}**: Actualizar valor.
    -   Body: `field_value`.
-   **DELETE /historial/fields-values/{id}**: Eliminar valor.

### Categorías de Historial

-   **GET /historial/history-categories**: Listar todas las categorías.
-   **POST /historial/history-categories**: Crear nueva categoría.
    -   Body: `name` (string, requerido, único)
-   **GET /historial/history-categories/{id}**: Ver categoría.
-   **PUT /historial/history-categories/{id}**: Actualizar nombre.
-   **DELETE /historial/history-categories/{id}**: Eliminar categoría.

### Uso de la Conexión Dinámica
````
