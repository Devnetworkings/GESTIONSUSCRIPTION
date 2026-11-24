# Diagrama entidad-relación

El diagrama corresponde a las tablas definidas en [`database/schema.sql`](../database/schema.sql).
La versión gráfica descargable está en [diagrama-er.svg](../diagrama-er.svg).
`PK` y `UK` indican claves primarias y únicas declaradas en SQL. Las relaciones
representan asociaciones utilizadas por la aplicación: el esquema actual no
declara restricciones `FOREIGN KEY`, por lo que la base de datos no las hace
cumplir automáticamente.

```mermaid
erDiagram
    CLIENTES {
        string id PK
        string tipo_documento
        string ci
        string nombres
        string apellidos
        string tlf
        string email
        string direccion
        string estatus
        date fecha_vencimiento
    }

    OPERATOR {
        string id PK
        string ci UK
        string nombres
        string apellidos
        string contacto
        datetime contacto_verified_at
        string direccion
        string PW
        string TLF UK
        string username UK
        string operator_role
        boolean activo
        string operator_status
        date vacation_start
        date vacation_end
        int login_failed_attempts
        datetime login_locked_until
    }

    PLANES {
        int id PK
        string nombre UK
        int duracion_meses
        decimal precio
        boolean activo
        string coin
    }

    PAGOS {
        string id PK
        string id_cliente FK
        string reference UK
        decimal monto
        string coin
        string id_operador FK
        date fecha_pago
        datetime fecha_registro
        string metodo_pago
        int id_plan FK
        decimal monto_usd
        decimal tasa_bcv
        date fecha_valor_bcv
        string plan_nombre_snapshot
        int plan_duracion_meses_snapshot
    }

    APP_SETTINGS {
        string setting_key PK
        string setting_value
    }

    AUDIT_LOGS {
        int id PK
        string actor_operator_id FK
        string actor_username
        string action
        string entity_type
        string entity_id
        text changes
        datetime created_at
    }

    EMAIL_VERIFICATION_TOKENS {
        int id PK
        string operator_id FK
        string token_hash UK
        datetime expires_at
        datetime created_at
    }

    PASSWORD_RESET_TOKENS {
        int id PK
        string operator_id FK
        string token_hash UK
        datetime expires_at
        datetime created_at
    }

    CLIENTES ||--o{ PAGOS : "realiza"
    OPERATOR ||--o{ PAGOS : "registra"
    PLANES o|--o{ PAGOS : "se aplica"
    OPERATOR ||--o{ EMAIL_VERIFICATION_TOKENS : "verifica correo"
    OPERATOR ||--o{ PASSWORD_RESET_TOKENS : "restablece clave"
    OPERATOR o|--o{ AUDIT_LOGS : "realiza"
```

En `PAGOS`, `id_plan` es opcional; sus campos `plan_*_snapshot` conservan la
información del plan al registrar el pago. `AUDIT_LOGS.actor_operator_id`
también es opcional. `PASSWORD_RESET_TOKENS.operator_id` está definido como
`VARCHAR(64)`, mientras que `OPERATOR.id` es `CHAR(36)`; la aplicación los
relaciona por valor, pero SQL no valida esa correspondencia.

`APP_SETTINGS` no tiene relaciones con otras tablas.
En `CLIENTES`, la unicidad está definida para el par
`(tipo_documento, ci)`, no para `ci` por separado.

En `PAGOS`, `fecha_pago` representa el día en que ocurrió la transacción y
`fecha_registro` conserva el momento exacto en que fue guardada por el sistema.
