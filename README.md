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
