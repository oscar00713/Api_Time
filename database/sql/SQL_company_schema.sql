CREATE TABLE global_options (
    horarios_negocio JSONB
);

CREATE TABLE block_appointments(
    id SERIAL PRIMARY KEY,
    day DATE NOT NULL,
    time TIME NOT NULL,
    user_id INTEGER NOT NULL,
    service_id INTEGER NOT NULL,
    employee_id INTEGER NOT NULL,
    CONSTRAINT fk_usersid FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_serviceid FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE CASCADE,
    CONSTRAINT fk_employeeid FOREIGN KEY (employee_id) REFERENCES users(id) ON DELETE CASCADE
);


CREATE TABLE users (
    id SERIAL PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    hash VARCHAR(255) NOT NULL,
    user_type VARCHAR(100) NOT NULL,
    active BOOLEAN NOT NULL DEFAULT true,
    phone VARCHAR(255),
    registration VARCHAR(100),-- DNI, NIF, PASAPORTE, Matricula
    fixed_salary DECIMAL(10, 2) DEFAULT 0,
    badge_color VARCHAR(100) DEFAULT '#666666',
    fixed_salary_frecuency VARCHAR(100),
   -- payday_type VARCHAR(100),
   -- paydar_number_of_days INTEGER,
    manage_salary BOOLEAN DEFAULT false,
    use_room BOOLEAN DEFAULT false
);

CREATE TABLE users_temp(
    id SERIAL PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    hash VARCHAR(255) NOT NULL,
    user_type VARCHAR(100) NOT NULL,
    active BOOLEAN NOT NULL DEFAULT true,
    phone VARCHAR(255),
    registration VARCHAR(100),-- DNI, NIF, PASAPORTE, Matricula
    fixed_salary DECIMAL(10, 2) DEFAULT 0,
    badge_color VARCHAR(100) DEFAULT '#666666',
    fixed_salary_frecuency VARCHAR(100),
   -- payday_type VARCHAR(100),
   -- paydar_number_of_days INTEGER,
    manage_salary BOOLEAN DEFAULT false,
    use_room BOOLEAN DEFAULT false,
    roles JSONB
);

CREATE TABLE roles (
    user_id INTEGER NOT NULL,
    manage_services BOOLEAN DEFAULT false,
    manage_users BOOLEAN DEFAULT false,
    manage_cash BOOLEAN DEFAULT false,
    view_client_history BOOLEAN DEFAULT false,
    create_client_history BOOLEAN DEFAULT false,
    cancel_client_history BOOLEAN DEFAULT false,
    appointments_own BOOLEAN DEFAULT false,
    appointments_other BOOLEAN DEFAULT false,
    appointments_self_assign BOOLEAN DEFAULT false,
    appointments_self_others BOOLEAN DEFAULT false,
    appointments_cancel_own BOOLEAN DEFAULT false,
    appointments_cancel_others BOOLEAN DEFAULT false,
    appointments_reschedule_own BOOLEAN DEFAULT false,
    appointments_reschedule_others BOOLEAN DEFAULT false,
    history_view BOOLEAN DEFAULT false,
    history_create BOOLEAN DEFAULT false,
    history_edit BOOLEAN DEFAULT false,
    history_delete BOOLEAN DEFAULT false,
    employees_create BOOLEAN DEFAULT false,
    employees_edit BOOLEAN DEFAULT false,
    employees_delete BOOLEAN DEFAULT false,
    manage_register BOOLEAN DEFAULT false,
    edit_money_own BOOLEAN DEFAULT false,
    edit_money_any BOOLEAN DEFAULT false,
    audit_register BOOLEAN DEFAULT false,
    view_reports BOOLEAN DEFAULT false,
    generate_reports BOOLEAN DEFAULT false,
    delete_reports BOOLEAN DEFAULT false,
    stock_add BOOLEAN DEFAULT false,
    stock_edit BOOLEAN DEFAULT false,
    CONSTRAINT fk_usersid FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE clients (
    id SERIAL PRIMARY KEY,
    first_name VARCHAR(100),
    last_name VARCHAR(100),
    birthday DATE,
    email VARCHAR(255),
    phone VARCHAR(255),
    national_id VARCHAR(30),
    receive_notifications_email BOOLEAN DEFAULT true,
    receive_notifications_whatsapp BOOLEAN DEFAULT true,
    total_invoices INTEGER DEFAULT 0,
    times_here INTEGER DEFAULT 0,
    total_spent DECIMAL(10, 2) DEFAULT 0,
    owe_money DECIMAL(10, 2) DEFAULT 0,
    banned BOOLEAN DEFAULT false,
    banned_reason VARCHAR(100)
);

--insertar clients con nombre anonymo

INSERT INTO clients (
    first_name,
    last_name,
    birthday,
    email,
    phone,
    national_id,
    receive_notifications_email,
    receive_notifications_whatsapp
) VALUES (
    'Anonymous',
    '',
    '2000-01-01',
    'anonymous@anonymous.com',
    '000000000',
    'ANON000',
    false,
    false
);

CREATE TABLE commissions (
    id SERIAL PRIMARY KEY,
    mes_anio DATE NOT NULL,
    user_id INTEGER NOT NULL,
    ammount_by_service_comissiones NUMERIC(5,2) NOT NULL DEFAULT 0,
    user_comission_percentage DECIMAL(5, 2) NOT NULL DEFAULT 0,
    user_comission_fixed DECIMAL(10, 2),
    note VARCHAR(100) NOT NULL,
    ammount DECIMAL(10, 2) NOT NULL
);

create TABLE cash_registers (
    id SERIAL PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    ammount DECIMAL(10, 2) NOT NULL
);

create TABLE cash_audits (
    id SERIAL PRIMARY KEY,
    date TIMESTAMP DEFAULT NOW(),
    ammount_registered_before_withdraw DECIMAL(10, 2) NOT NULL,
    ammount_to_withdraw DECIMAL(10, 2) NOT NULL,
    ammount_registered_after_withdraw DECIMAL(10, 2) NOT NULL,
    user_id INTEGER NOT NULL
);

CREATE TABLE services (
    id SERIAL PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    appointment_duration_minutes INTEGER NOT NULL,
    service_price DECIMAL(10, 2) DEFAULT 0,
    sort_order INTEGER DEFAULT 50,
    active BOOLEAN DEFAULT true
);

-- CREATE TABLE users (
--     id SERIAL PRIMARY KEY,
--     name VARCHAR(100) NOT NULL,
--     fixed_salary DECIMAL(10, 2) DEFAULT 0,
--     user_id INTEGER DEFAULT 0,
--     user_type VARCHAR(100) NOT NULL,
--     invitation_email VARCHAR(255) DEFAULT NULL,
--     badge_color VARCHAR(100) DEFAULT '#666666',
--     active BOOLEAN DEFAULT true
-- );

CREATE TABLE user_services (
    service_id INTEGER NOT NULL,
    user_id INTEGER NOT NULL,
    -- horarios JSONB NOT NULL,
    commission_type VARCHAR(100) NOT NULL DEFAULT 'none', /* 0 = no comission, 1 = fixed, 2 = percentage, 3 = percentage plus fixed amount */
    percentage NUMERIC(5,2) DEFAULT 0,
    fixed DECIMAL(10, 2) DEFAULT 0,
    PRIMARY KEY (service_id, user_id),
    CONSTRAINT fk_usersid FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_services FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE CASCADE
);

CREATE TABLE rangos(
    id SERIAL PRIMARY KEY,
    service_id INTEGER NOT NULL,
    monday BOOLEAN NOT NULL,
    tuesday BOOLEAN NOT NULL,
    wednesday BOOLEAN NOT NULL,
    thursday BOOLEAN NOT NULL,
    friday BOOLEAN NOT NULL,
    saturday BOOLEAN NOT NULL,
    sunday BOOLEAN NOT NULL,
    CONSTRAINT fk_service FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE CASCADE
);

CREATE TABLE user_range(
    range_id INTEGER NOT NULL,
    user_id INTEGER NOT NULL,
    CONSTRAINT fk_range FOREIGN KEY (range_id) REFERENCES rangos(id) ON DELETE CASCADE,
    CONSTRAINT fk_usersid FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE times_range(
     range_id INTEGER NOT NULL,
     hora_inicio  TIME NOT NULL,
     hora_fim  TIME NOT NULL,
     CONSTRAINT fk_range FOREIGN KEY (range_id) REFERENCES rangos(id) ON DELETE CASCADE

);

CREATE TABLE reminders (
    id SERIAL PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    date TIMESTAMP NOT NULL,
    user_id INTEGER DEFAULT 0,
    everybody BOOLEAN DEFAULT false,
    badge_color VARCHAR(100) DEFAULT '#666666',
    seen BOOLEAN DEFAULT false,
    deactivated BOOLEAN DEFAULT false
);

CREATE TABLE appointments (
    id INTEGER,
    date TIMESTAMP NOT NULL PRIMARY KEY,
    client_id INTEGER NOT NULL,
    service_id INTEGER NOT NULL,
    user_id INTEGER NOT NULL,
    user_comission_applied INTEGER,
    user_comission DECIMAL(10, 2),
    user_comission_percentage NUMERIC(5,2) NOT NULL DEFAULT 0.00,
    user_comission_fixed DECIMAL(10, 2),
    status INTEGER NOT NULL,
    start_date TIMESTAMP NOT NULL,
    end_date TIMESTAMP NOT NULL,
    CONSTRAINT fk_client FOREIGN KEY (client_id) REFERENCES clients(id),
    CONSTRAINT fk_service FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE CASCADE,
    CONSTRAINT fk_users FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) PARTITION BY RANGE (date);


CREATE TABLE appointments_default PARTITION OF appointments DEFAULT;

CREATE OR REPLACE FUNCTION create_partition_and_insert()
RETURNS TRIGGER AS $$
DECLARE
    partition_name TEXT;
    start_date TIMESTAMP;
    end_date TIMESTAMP;
BEGIN
    partition_name = 'z_appo_' + TO_CHAR(NEW.date, 'YYYY_MM');
    start_date := DATE_TRUNC('month', NEW.date);
    end_date := start_date + INTERVAL '1 month';
    IF NOT EXISTS (SELECT 1 FROM information_schema.tables WHERE table_name = partition_name) THEN
        EXECUTE format('
            CREATE TABLE %I PARTITION OF appointments
            FOR VALUES FROM (%L) TO (%L)',
            partition_name, start_date, end_date);
        EXECUTE format('CREATE INDEX idx_user_id_%I ON %I(user_user_id)', partition_name, partition_name);
        EXECUTE format('CREATE INDEX idx_client_id_%I ON %I(client_id)', partition_name, partition_name);
        EXECUTE format('CREATE INDEX idx_service_id_%I ON %I(service_id)', partition_name, partition_name);
    END IF;
    EXECUTE format('INSERT INTO %I VALUES ($1.*)', partition_name) USING NEW;
    RETURN NULL;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER insert_appointments_trigger
BEFORE INSERT ON appointments_default
FOR EACH ROW EXECUTE FUNCTION create_partition_and_insert();
