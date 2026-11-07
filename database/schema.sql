-- ITAM database schema (SQLite and MySQL/MariaDB). Later changes go in database/migrations/*.sql, applied in name order.
-- Statements starting with /*mysql*/ run on MySQL/MariaDB only, /*sqlite*/ on SQLite only.


-- from schema.sql
CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name VARCHAR(120) NOT NULL,
    email VARCHAR(190) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role VARCHAR(20) NOT NULL DEFAULT 'technician',
    locale VARCHAR(5) NOT NULL DEFAULT 'en',
    theme VARCHAR(10) NOT NULL DEFAULT 'system',
    active INTEGER NOT NULL DEFAULT 1,
    last_login_at VARCHAR(20),
    created_at VARCHAR(20) NOT NULL
);
CREATE TABLE IF NOT EXISTS departments (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name VARCHAR(120) NOT NULL,
    code VARCHAR(20)
);
CREATE TABLE IF NOT EXISTS locations (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name VARCHAR(120) NOT NULL,
    city VARCHAR(80),
    building VARCHAR(80),
    floor VARCHAR(20),
    room VARCHAR(40)
);
CREATE TABLE IF NOT EXISTS categories (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name VARCHAR(120) NOT NULL,
    code VARCHAR(10) NOT NULL,
    icon VARCHAR(30) NOT NULL DEFAULT 'box',
    lifespan_months INTEGER NOT NULL DEFAULT 48,
    power_watts INTEGER NOT NULL DEFAULT 0,
    hours_per_day INTEGER NOT NULL DEFAULT 8,
    maintenance_interval_days INTEGER NOT NULL DEFAULT 0
);
CREATE TABLE IF NOT EXISTS employees (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name VARCHAR(120) NOT NULL,
    employee_no VARCHAR(40),
    email VARCHAR(190),
    title VARCHAR(120),
    department_id INTEGER REFERENCES departments(id) ON DELETE SET NULL,
    location_id INTEGER REFERENCES locations(id) ON DELETE SET NULL,
    active INTEGER NOT NULL DEFAULT 1
);
CREATE TABLE IF NOT EXISTS assets (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    tag VARCHAR(40) NOT NULL UNIQUE,
    name VARCHAR(160) NOT NULL,
    category_id INTEGER REFERENCES categories(id) ON DELETE SET NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'in_stock',
    condition_state VARCHAR(20) NOT NULL DEFAULT 'good',
    criticality INTEGER NOT NULL DEFAULT 2,
    manufacturer VARCHAR(80),
    model VARCHAR(120),
    serial VARCHAR(120),
    hostname VARCHAR(120),
    ip_address VARCHAR(45),
    mac_address VARCHAR(32),
    os VARCHAR(120),
    cpu VARCHAR(120),
    ram_gb INTEGER,
    storage_gb INTEGER,
    location_id INTEGER REFERENCES locations(id) ON DELETE SET NULL,
    department_id INTEGER REFERENCES departments(id) ON DELETE SET NULL,
    assigned_to INTEGER REFERENCES employees(id) ON DELETE SET NULL,
    acquired_at VARCHAR(10),
    warranty_until VARCHAR(10),
    value NUMERIC,
    power_watts INTEGER,
    notes TEXT,
    source VARCHAR(20) NOT NULL DEFAULT 'manual',
    last_seen_at VARCHAR(20),
    health_score INTEGER,
    failure_risk INTEGER,
    predicted_replacement VARCHAR(10),
    health_factors TEXT,
    created_at VARCHAR(20) NOT NULL,
    updated_at VARCHAR(20) NOT NULL
);
CREATE INDEX IF NOT EXISTS idx_assets_status ON assets(status);
CREATE INDEX IF NOT EXISTS idx_assets_category ON assets(category_id);
CREATE INDEX IF NOT EXISTS idx_assets_serial ON assets(serial);
CREATE INDEX IF NOT EXISTS idx_assets_hostname ON assets(hostname);
CREATE TABLE IF NOT EXISTS assignments (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    asset_id INTEGER NOT NULL REFERENCES assets(id) ON DELETE CASCADE,
    employee_id INTEGER NOT NULL REFERENCES employees(id) ON DELETE CASCADE,
    checked_out_at VARCHAR(20) NOT NULL,
    expected_return VARCHAR(10),
    checked_in_at VARCHAR(20),
    condition_out VARCHAR(20),
    condition_in VARCHAR(20),
    notes TEXT,
    user_id INTEGER REFERENCES users(id) ON DELETE SET NULL
);
CREATE INDEX IF NOT EXISTS idx_assignments_asset ON assignments(asset_id);
CREATE TABLE IF NOT EXISTS maintenance (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    asset_id INTEGER NOT NULL REFERENCES assets(id) ON DELETE CASCADE,
    type VARCHAR(20) NOT NULL DEFAULT 'corrective',
    title VARCHAR(200) NOT NULL,
    description TEXT,
    status VARCHAR(20) NOT NULL DEFAULT 'open',
    priority VARCHAR(10) NOT NULL DEFAULT 'medium',
    scheduled_for VARCHAR(10),
    completed_at VARCHAR(20),
    downtime_hours NUMERIC NOT NULL DEFAULT 0,
    technician_id INTEGER REFERENCES users(id) ON DELETE SET NULL,
    recurrence_days INTEGER NOT NULL DEFAULT 0,
    created_at VARCHAR(20) NOT NULL
);
CREATE INDEX IF NOT EXISTS idx_maintenance_asset ON maintenance(asset_id);
CREATE TABLE IF NOT EXISTS licenses (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name VARCHAR(160) NOT NULL,
    publisher VARCHAR(120),
    edition VARCHAR(80),
    license_type VARCHAR(20) NOT NULL DEFAULT 'per_device',
    license_key TEXT,
    seats INTEGER NOT NULL DEFAULT 1,
    expires_at VARCHAR(10),
    notes TEXT,
    created_at VARCHAR(20) NOT NULL
);
CREATE TABLE IF NOT EXISTS license_seats (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    license_id INTEGER NOT NULL REFERENCES licenses(id) ON DELETE CASCADE,
    asset_id INTEGER REFERENCES assets(id) ON DELETE CASCADE,
    employee_id INTEGER REFERENCES employees(id) ON DELETE CASCADE,
    last_used_at VARCHAR(20),
    assigned_at VARCHAR(20) NOT NULL
);
CREATE TABLE IF NOT EXISTS audits (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name VARCHAR(160) NOT NULL,
    location_id INTEGER REFERENCES locations(id) ON DELETE SET NULL,
    status VARCHAR(10) NOT NULL DEFAULT 'open',
    started_by INTEGER REFERENCES users(id) ON DELETE SET NULL,
    started_at VARCHAR(20) NOT NULL,
    closed_at VARCHAR(20)
);
CREATE TABLE IF NOT EXISTS audit_items (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    audit_id INTEGER NOT NULL REFERENCES audits(id) ON DELETE CASCADE,
    asset_id INTEGER REFERENCES assets(id) ON DELETE CASCADE,
    scanned_value VARCHAR(160),
    result VARCHAR(20) NOT NULL,
    scanned_at VARCHAR(20) NOT NULL
);
CREATE TABLE IF NOT EXISTS notifications (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER REFERENCES users(id) ON DELETE CASCADE,
    type VARCHAR(30) NOT NULL,
    title VARCHAR(200) NOT NULL,
    body TEXT,
    link VARCHAR(255),
    fingerprint VARCHAR(80),
    read_at VARCHAR(20),
    created_at VARCHAR(20) NOT NULL
);
CREATE INDEX IF NOT EXISTS idx_notifications_fp ON notifications(fingerprint);
CREATE TABLE IF NOT EXISTS ledger (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    created_at VARCHAR(20) NOT NULL,
    user_id INTEGER,
    user_name VARCHAR(120),
    action VARCHAR(40) NOT NULL,
    entity VARCHAR(40) NOT NULL,
    entity_id INTEGER,
    summary VARCHAR(255),
    payload TEXT,
    prev_hash VARCHAR(64) NOT NULL,
    hash VARCHAR(64) NOT NULL
);
CREATE INDEX IF NOT EXISTS idx_ledger_entity ON ledger(entity, entity_id);
CREATE TABLE IF NOT EXISTS api_tokens (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name VARCHAR(120) NOT NULL,
    token_hash VARCHAR(64) NOT NULL UNIQUE,
    last_used_at VARCHAR(20),
    created_at VARCHAR(20) NOT NULL
);
CREATE TABLE IF NOT EXISTS settings (
    skey VARCHAR(80) PRIMARY KEY,
    svalue TEXT
);

-- from 001_disposals_parts_attachments.sql
CREATE TABLE IF NOT EXISTS disposals (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    certificate_no VARCHAR(30) NOT NULL UNIQUE,
    asset_id INTEGER REFERENCES assets(id) ON DELETE SET NULL,
    asset_tag VARCHAR(40) NOT NULL,
    asset_name VARCHAR(160) NOT NULL,
    serial VARCHAR(120),
    category_name VARCHAR(120),
    reason VARCHAR(30) NOT NULL,
    method VARCHAR(20) NOT NULL,
    sanitization VARCHAR(20) NOT NULL,
    sanitization_tool VARCHAR(160),
    verified_by VARCHAR(120),
    recipient VARCHAR(160),
    notes TEXT,
    disposed_at VARCHAR(10) NOT NULL,
    user_id INTEGER REFERENCES users(id) ON DELETE SET NULL,
    ledger_hash VARCHAR(64),
    created_at VARCHAR(20) NOT NULL
);
CREATE TABLE IF NOT EXISTS attachments (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    asset_id INTEGER NOT NULL REFERENCES assets(id) ON DELETE CASCADE,
    kind VARCHAR(20) NOT NULL DEFAULT 'other',
    name VARCHAR(200) NOT NULL,
    stored_name VARCHAR(80) NOT NULL,
    mime VARCHAR(100),
    size INTEGER NOT NULL DEFAULT 0,
    user_id INTEGER REFERENCES users(id) ON DELETE SET NULL,
    created_at VARCHAR(20) NOT NULL
);
CREATE INDEX IF NOT EXISTS idx_attachments_asset ON attachments(asset_id);

-- from 002_lifecycle_service_infrastructure.sql
ALTER TABLE maintenance ADD COLUMN source VARCHAR(20) NOT NULL DEFAULT 'internal';
ALTER TABLE maintenance ADD COLUMN reporter_name VARCHAR(120);
ALTER TABLE maintenance ADD COLUMN reporter_contact VARCHAR(160);
ALTER TABLE maintenance ADD COLUMN reporter_ip VARCHAR(45);
ALTER TABLE maintenance ADD COLUMN due_at VARCHAR(20);
ALTER TABLE maintenance ADD COLUMN escalated_at VARCHAR(20);
ALTER TABLE maintenance ADD COLUMN resolution TEXT;
ALTER TABLE users ADD COLUMN totp_secret VARCHAR(64);
ALTER TABLE users ADD COLUMN totp_enabled INTEGER NOT NULL DEFAULT 0;
ALTER TABLE users ADD COLUMN recovery_codes TEXT;
ALTER TABLE employees ADD COLUMN external_id VARCHAR(190);
ALTER TABLE employees ADD COLUMN synced_at VARCHAR(20);
ALTER TABLE assets ADD COLUMN rack_id INTEGER;
ALTER TABLE assets ADD COLUMN rack_u INTEGER;
ALTER TABLE assets ADD COLUMN rack_height INTEGER;
ALTER TABLE assets ADD COLUMN plan_id INTEGER;
ALTER TABLE assets ADD COLUMN plan_x NUMERIC;
ALTER TABLE assets ADD COLUMN plan_y NUMERIC;
CREATE TABLE IF NOT EXISTS custom_fields (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    category_id INTEGER NOT NULL REFERENCES categories(id) ON DELETE CASCADE,
    label VARCHAR(80) NOT NULL,
    type VARCHAR(10) NOT NULL DEFAULT 'text',
    options TEXT,
    required INTEGER NOT NULL DEFAULT 0,
    sort INTEGER NOT NULL DEFAULT 0
);
CREATE TABLE IF NOT EXISTS custom_values (
    asset_id INTEGER NOT NULL REFERENCES assets(id) ON DELETE CASCADE,
    field_id INTEGER NOT NULL REFERENCES custom_fields(id) ON DELETE CASCADE,
    value TEXT,
    PRIMARY KEY (asset_id, field_id)
);
CREATE TABLE IF NOT EXISTS outbox (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    channel VARCHAR(10) NOT NULL,
    recipient VARCHAR(255),
    subject VARCHAR(255) NOT NULL,
    body TEXT,
    link VARCHAR(255),
    status VARCHAR(10) NOT NULL DEFAULT 'pending',
    error VARCHAR(255),
    created_at VARCHAR(20) NOT NULL,
    sent_at VARCHAR(20)
);

-- from 003_sso_discovery.sql
ALTER TABLE users ADD COLUMN auth_provider VARCHAR(10) NOT NULL DEFAULT 'local';
ALTER TABLE users ADD COLUMN external_id VARCHAR(190);

-- from 004_custody_receipts.sql
CREATE TABLE IF NOT EXISTS custody_receipts (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    receipt_no VARCHAR(30) NOT NULL UNIQUE,
    type VARCHAR(10) NOT NULL DEFAULT 'issue',
    employee_id INTEGER REFERENCES employees(id) ON DELETE SET NULL,
    employee_name VARCHAR(120) NOT NULL,
    items TEXT NOT NULL,
    terms TEXT,
    notes TEXT,
    status VARCHAR(10) NOT NULL DEFAULT 'pending',
    sign_token VARCHAR(64),
    token_expires VARCHAR(20),
    employee_signature VARCHAR(80),
    signer_name VARCHAR(120),
    signed_at VARCHAR(20),
    signed_ip VARCHAR(45),
    signed_via VARCHAR(10),
    staff_user_id INTEGER REFERENCES users(id) ON DELETE SET NULL,
    staff_signature VARCHAR(80),
    doc_hash VARCHAR(64),
    ledger_hash VARCHAR(64),
    created_at VARCHAR(20) NOT NULL
);
CREATE INDEX IF NOT EXISTS idx_custody_employee ON custody_receipts(employee_id);
CREATE INDEX IF NOT EXISTS idx_custody_token ON custody_receipts(sign_token);

-- from 005_telemetry_redistribution_portal.sql
CREATE TABLE IF NOT EXISTS portal_tokens (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    employee_id INTEGER NOT NULL REFERENCES employees(id) ON DELETE CASCADE,
    token_hash VARCHAR(64) NOT NULL,
    expires_at VARCHAR(20) NOT NULL,
    used_at VARCHAR(20),
    created_at VARCHAR(20) NOT NULL
);
CREATE INDEX IF NOT EXISTS idx_portal_tokens_hash ON portal_tokens(token_hash);

-- from 006_windows_anomalies_executive.sql
CREATE TABLE IF NOT EXISTS asset_moves (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    asset_id INTEGER NOT NULL REFERENCES assets(id) ON DELETE CASCADE,
    from_location_id INTEGER REFERENCES locations(id) ON DELETE SET NULL,
    to_location_id INTEGER REFERENCES locations(id) ON DELETE SET NULL,
    via VARCHAR(60),
    user_id INTEGER REFERENCES users(id) ON DELETE SET NULL,
    moved_at VARCHAR(20) NOT NULL
);
CREATE INDEX IF NOT EXISTS idx_moves_asset ON asset_moves(asset_id, moved_at);
ALTER TABLE outbox ADD COLUMN attachment VARCHAR(255);

-- from 007_operations_collaboration.sql
CREATE TABLE IF NOT EXISTS comments (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    entity VARCHAR(20) NOT NULL,
    entity_id INTEGER NOT NULL,
    user_id INTEGER REFERENCES users(id) ON DELETE SET NULL,
    body TEXT NOT NULL,
    created_at VARCHAR(20) NOT NULL
);
CREATE INDEX IF NOT EXISTS idx_comments_entity ON comments(entity, entity_id);
ALTER TABLE users ADD COLUMN location_scope TEXT;
ALTER TABLE attachments ADD COLUMN maintenance_id INTEGER;
ALTER TABLE maintenance ADD COLUMN signature TEXT;
ALTER TABLE maintenance ADD COLUMN signed_by VARCHAR(120);

-- from 008_purchase_order.sql
ALTER TABLE assets ADD COLUMN po_number VARCHAR(60);
CREATE INDEX IF NOT EXISTS idx_assets_po ON assets(po_number);

-- from 010_security_software_service.sql
ALTER TABLE licenses ADD COLUMN software_match VARCHAR(255);
ALTER TABLE assets ADD COLUMN loanable INTEGER NOT NULL DEFAULT 0;
CREATE TABLE IF NOT EXISTS loan_bookings (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    number VARCHAR(20) NOT NULL,
    asset_id INTEGER NOT NULL REFERENCES assets(id) ON DELETE CASCADE,
    employee_id INTEGER NOT NULL REFERENCES employees(id) ON DELETE CASCADE,
    starts_on VARCHAR(10) NOT NULL,
    ends_on VARCHAR(10) NOT NULL,
    purpose VARCHAR(255),
    status VARCHAR(12) NOT NULL DEFAULT 'requested',
    via VARCHAR(10) NOT NULL DEFAULT 'staff',
    checked_out_at VARCHAR(20),
    returned_at VARCHAR(20),
    reminded_at VARCHAR(20),
    user_id INTEGER REFERENCES users(id) ON DELETE SET NULL,
    created_at VARCHAR(20) NOT NULL,
    updated_at VARCHAR(20) NOT NULL
);
CREATE INDEX IF NOT EXISTS idx_loans_asset ON loan_bookings(asset_id, starts_on, ends_on);
ALTER TABLE maintenance ADD COLUMN cost REAL;
CREATE TABLE IF NOT EXISTS consumables (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name VARCHAR(160) NOT NULL,
    sku VARCHAR(60),
    kind VARCHAR(20) NOT NULL DEFAULT 'accessory',
    unit VARCHAR(20),
    quantity INTEGER NOT NULL DEFAULT 0,
    min_quantity INTEGER NOT NULL DEFAULT 0,
    unit_cost REAL,
    location_id INTEGER REFERENCES locations(id) ON DELETE SET NULL,
    notes TEXT,
    alerted_at VARCHAR(20),
    created_at VARCHAR(20) NOT NULL,
    updated_at VARCHAR(20) NOT NULL
);
CREATE TABLE IF NOT EXISTS consumable_moves (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    consumable_id INTEGER NOT NULL REFERENCES consumables(id) ON DELETE CASCADE,
    change_qty INTEGER NOT NULL,
    balance INTEGER NOT NULL,
    reason VARCHAR(10) NOT NULL,
    employee_id INTEGER REFERENCES employees(id) ON DELETE SET NULL,
    request_id INTEGER,
    note VARCHAR(255),
    user_id INTEGER REFERENCES users(id) ON DELETE SET NULL,
    created_at VARCHAR(20) NOT NULL
);
CREATE INDEX IF NOT EXISTS idx_consumable_moves ON consumable_moves(consumable_id, created_at);
ALTER TABLE audits ADD COLUMN category_id INTEGER;
ALTER TABLE audits ADD COLUMN plan_id INTEGER;

-- from 012_login_throttle_outbox_retry.sql
CREATE TABLE IF NOT EXISTS login_failures (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    email VARCHAR(190) NOT NULL,
    ip VARCHAR(64) NOT NULL,
    created_at VARCHAR(20) NOT NULL
);
CREATE INDEX IF NOT EXISTS idx_login_failures_email ON login_failures(email, created_at);
CREATE INDEX IF NOT EXISTS idx_login_failures_ip ON login_failures(ip, created_at);
ALTER TABLE outbox ADD COLUMN attempts INTEGER NOT NULL DEFAULT 0;
ALTER TABLE outbox ADD COLUMN next_attempt_at VARCHAR(20);

-- from 014_whatsapp.sql
ALTER TABLE employees ADD COLUMN mobile VARCHAR(20);
ALTER TABLE employees ADD COLUMN whatsapp_optout INTEGER NOT NULL DEFAULT 0;
CREATE INDEX IF NOT EXISTS idx_employees_mobile ON employees(mobile);
ALTER TABLE users ADD COLUMN mobile VARCHAR(20);
ALTER TABLE users ADD COLUMN whatsapp_notify INTEGER NOT NULL DEFAULT 0;
ALTER TABLE outbox ADD COLUMN locale VARCHAR(5);
ALTER TABLE outbox ADD COLUMN kind VARCHAR(10);

-- from 015_finance.sql
ALTER TABLE categories ADD COLUMN support_cost_yearly REAL;
ALTER TABLE categories ADD COLUMN asset_class VARCHAR(20);
ALTER TABLE licenses ADD COLUMN seat_cost REAL;
ALTER TABLE licenses ADD COLUMN cost_period VARCHAR(10) NOT NULL DEFAULT 'year';

-- from 016_procurement.sql
ALTER TABLE assets ADD COLUMN vendor_id INTEGER;
ALTER TABLE assets ADD COLUMN po_line_id INTEGER;
CREATE INDEX IF NOT EXISTS idx_assets_vendor ON assets(vendor_id);
CREATE INDEX IF NOT EXISTS idx_assets_po_line ON assets(po_line_id);

-- from 018_portal_conversation.sql
ALTER TABLE comments ADD COLUMN portal INTEGER NOT NULL DEFAULT 0;
ALTER TABLE comments ADD COLUMN employee_id INTEGER;
ALTER TABLE comments ADD COLUMN attachment_id INTEGER;
ALTER TABLE maintenance ADD COLUMN requested_date VARCHAR(10);
ALTER TABLE maintenance ADD COLUMN requested_slot VARCHAR(10);
ALTER TABLE maintenance ADD COLUMN requested_note VARCHAR(255);
ALTER TABLE employees ADD COLUMN notify_email INTEGER NOT NULL DEFAULT 1;
ALTER TABLE employees ADD COLUMN locale VARCHAR(5);

-- from 019_portal_team_attest.sql
ALTER TABLE employees ADD COLUMN manager_id INTEGER;
CREATE INDEX IF NOT EXISTS idx_employees_manager ON employees(manager_id);

-- from 020_entra_roles.sql
ALTER TABLE users ADD COLUMN directory_id VARCHAR(64);
ALTER TABLE users ADD COLUMN managed_by VARCHAR(10);

-- from 021_roles.sql
CREATE TABLE IF NOT EXISTS roles (
    slug VARCHAR(30) PRIMARY KEY,
    name_en VARCHAR(80),
    name_ar VARCHAR(80),
    permissions TEXT NOT NULL,
    entra_group VARCHAR(36),
    sort INTEGER NOT NULL DEFAULT 0,
    created_at VARCHAR(20) NOT NULL,
    updated_at VARCHAR(20) NOT NULL
);

-- from 022_password_resets.sql
CREATE TABLE IF NOT EXISTS password_resets (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER REFERENCES users(id) ON DELETE CASCADE,
    token_hash VARCHAR(64) NOT NULL,
    ip VARCHAR(64),
    expires_at VARCHAR(20) NOT NULL,
    used_at VARCHAR(20),
    created_at VARCHAR(20) NOT NULL
);
CREATE INDEX IF NOT EXISTS idx_password_resets_token ON password_resets(token_hash);

-- from 023_token_scopes.sql
ALTER TABLE api_tokens ADD COLUMN scopes VARCHAR(100);

-- from 024_performance_indexes.sql
CREATE INDEX IF NOT EXISTS idx_assets_assigned ON assets(assigned_to);
CREATE INDEX IF NOT EXISTS idx_assets_location ON assets(location_id);
CREATE INDEX IF NOT EXISTS idx_assets_warranty ON assets(warranty_until);
CREATE INDEX IF NOT EXISTS idx_assets_department ON assets(department_id);
CREATE INDEX IF NOT EXISTS idx_maintenance_status ON maintenance(status, created_at);
CREATE INDEX IF NOT EXISTS idx_maintenance_reporter ON maintenance(reporter_contact);
CREATE INDEX IF NOT EXISTS idx_maintenance_technician ON maintenance(technician_id);
CREATE INDEX IF NOT EXISTS idx_employees_location ON employees(location_id);
CREATE INDEX IF NOT EXISTS idx_employees_department ON employees(department_id);
CREATE INDEX IF NOT EXISTS idx_employees_external ON employees(external_id);
CREATE INDEX IF NOT EXISTS idx_assignments_employee ON assignments(employee_id);
CREATE INDEX IF NOT EXISTS idx_license_seats_employee ON license_seats(employee_id);
CREATE INDEX IF NOT EXISTS idx_license_seats_license ON license_seats(license_id);
CREATE INDEX IF NOT EXISTS idx_ledger_hash ON ledger(hash);
CREATE INDEX IF NOT EXISTS idx_ledger_created ON ledger(created_at);
CREATE INDEX IF NOT EXISTS idx_outbox_status ON outbox(status);
CREATE INDEX IF NOT EXISTS idx_notifications_user ON notifications(user_id, id);

-- from 025_manufacturers_models.sql
CREATE TABLE IF NOT EXISTS manufacturers (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name VARCHAR(80) NOT NULL,
    website VARCHAR(255),
    support_phone VARCHAR(40)
);
CREATE INDEX IF NOT EXISTS idx_manufacturers_name ON manufacturers(name);
CREATE TABLE IF NOT EXISTS asset_models (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    manufacturer_id INTEGER NOT NULL REFERENCES manufacturers(id) ON DELETE CASCADE,
    category_id INTEGER REFERENCES categories(id) ON DELETE SET NULL,
    name VARCHAR(120) NOT NULL
);
CREATE INDEX IF NOT EXISTS idx_asset_models_manufacturer ON asset_models(manufacturer_id);
CREATE INDEX IF NOT EXISTS idx_assets_manufacturer ON assets(manufacturer);

-- from 027_work_order_link.sql
ALTER TABLE maintenance ADD COLUMN ticket_id INTEGER;
CREATE INDEX IF NOT EXISTS idx_maintenance_ticket ON maintenance(ticket_id);

-- from 032_itsm_data_migration.sql
CREATE INDEX IF NOT EXISTS idx_maintenance_type ON maintenance(type, status, created_at);

-- from 039_issue_categories.sql
ALTER TABLE maintenance ADD COLUMN issue_category_id INTEGER;

-- from 040_vip.sql
ALTER TABLE employees ADD COLUMN grade_id INTEGER;
ALTER TABLE asset_models ADD COLUMN specs VARCHAR(255);
ALTER TABLE asset_models ADD COLUMN image_url VARCHAR(500);

-- from 041_custody_reminders.sql
ALTER TABLE custody_receipts ADD COLUMN reminded_at VARCHAR(20);

-- from 042_org_structure.sql
ALTER TABLE departments ADD COLUMN level VARCHAR(10) NOT NULL DEFAULT 'department';
ALTER TABLE departments ADD COLUMN parent_id INTEGER REFERENCES departments(id) ON DELETE SET NULL;

-- from 043_procurement_cycle.sql
ALTER TABLE departments ADD COLUMN head_user_id INTEGER;

-- from 044_grn_invoices.sql
ALTER TABLE assets ADD COLUMN po_receipt_id INTEGER;
CREATE INDEX IF NOT EXISTS idx_assets_po_receipt ON assets(po_receipt_id);

-- from 052_agreement_alerts_reorder.sql
ALTER TABLE consumables ADD COLUMN reorder_qty INTEGER NOT NULL DEFAULT 0;

-- from 059_finance.sql
CREATE TABLE IF NOT EXISTS rate_hits (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    bucket VARCHAR(120) NOT NULL,
    created_at VARCHAR(20) NOT NULL
);
CREATE INDEX IF NOT EXISTS idx_rate_hits_bucket ON rate_hits(bucket, created_at);
/*mysql*/ ALTER TABLE users MODIFY totp_secret VARCHAR(255);
ALTER TABLE users ADD COLUMN finance_scope VARCHAR(500);

-- from 061_catalog_arabic.sql
ALTER TABLE categories ADD COLUMN name_ar VARCHAR(120);

-- from 069_agent_workspace.sql
ALTER TABLE users ADD COLUMN desk_away INTEGER NOT NULL DEFAULT 0;
ALTER TABLE users ADD COLUMN desk_capacity INTEGER;

-- from 074_hr_core.sql
ALTER TABLE employees ADD COLUMN position_id INTEGER;
ALTER TABLE employees ADD COLUMN name_ar VARCHAR(120);
ALTER TABLE employees ADD COLUMN nationality VARCHAR(2);
ALTER TABLE employees ADD COLUMN gender VARCHAR(1);
ALTER TABLE employees ADD COLUMN birth_date VARCHAR(10);
ALTER TABLE employees ADD COLUMN marital_status VARCHAR(10);
ALTER TABLE employees ADD COLUMN id_type VARCHAR(10);
ALTER TABLE employees ADD COLUMN id_sealed TEXT;
ALTER TABLE employees ADD COLUMN id_last4 VARCHAR(4);
ALTER TABLE employees ADD COLUMN hire_date VARCHAR(10);
ALTER TABLE employees ADD COLUMN employment_type VARCHAR(12);
ALTER TABLE employees ADD COLUMN hr_status VARCHAR(12);
ALTER TABLE employees ADD COLUMN probation_end VARCHAR(10);
ALTER TABLE employees ADD COLUMN end_date VARCHAR(10);
ALTER TABLE employees ADD COLUMN end_reason VARCHAR(20);
ALTER TABLE employees ADD COLUMN hr_reminded VARCHAR(40);
CREATE INDEX IF NOT EXISTS idx_employees_position ON employees(position_id);
CREATE INDEX IF NOT EXISTS idx_employees_hr_status ON employees(hr_status);

-- from 075_hr_time.sql
ALTER TABLE employees ADD COLUMN schedule_id INTEGER;
ALTER TABLE employees ADD COLUMN religion VARCHAR(10);

-- from 076_hr_payroll.sql
ALTER TABLE employees ADD COLUMN bank_name VARCHAR(80);
ALTER TABLE employees ADD COLUMN iban_sealed TEXT;
ALTER TABLE employees ADD COLUMN iban_last4 VARCHAR(4);
ALTER TABLE employees ADD COLUMN gosi_scheme VARCHAR(4);

-- from 077_hr_self_service.sql
ALTER TABLE employees ADD COLUMN address VARCHAR(255);
ALTER TABLE employees ADD COLUMN emergency_name VARCHAR(120);
ALTER TABLE employees ADD COLUMN emergency_phone VARCHAR(40);

-- from 078_hr_recruitment.sql
ALTER TABLE assets ADD COLUMN reserved_for INTEGER;

-- from 079_hr_talent.sql
ALTER TABLE employees ADD COLUMN potential VARCHAR(8);
ALTER TABLE employees ADD COLUMN flight_risk VARCHAR(8);

-- from 080_hr_insights.sql
ALTER TABLE employees ADD COLUMN disability INTEGER NOT NULL DEFAULT 0;

-- from 100_itsm_autonomy.sql
ALTER TABLE assets ADD COLUMN agent_jobs_at VARCHAR(20);

-- from 101_vulnerabilities.sql
ALTER TABLE assets ADD COLUMN os_build VARCHAR(24);

-- from 103_inventory.sql
ALTER TABLE consumables ADD COLUMN barcode VARCHAR(64);
ALTER TABLE consumables ADD COLUMN max_quantity INTEGER NOT NULL DEFAULT 0;
ALTER TABLE consumables ADD COLUMN reorder_point INTEGER NOT NULL DEFAULT 0;
ALTER TABLE consumables ADD COLUMN avg_cost DECIMAL(14,4);
ALTER TABLE consumables ADD COLUMN category VARCHAR(80);
ALTER TABLE consumables ADD COLUMN compatible_with VARCHAR(255);
ALTER TABLE consumables ADD COLUMN active INTEGER NOT NULL DEFAULT 1;
ALTER TABLE consumables ADD COLUMN part_id INTEGER;
CREATE INDEX IF NOT EXISTS idx_consumables_barcode ON consumables(barcode);
CREATE INDEX IF NOT EXISTS idx_consumables_sku ON consumables(sku);
ALTER TABLE consumable_moves ADD COLUMN maintenance_id INTEGER;
ALTER TABLE consumable_moves ADD COLUMN asset_id INTEGER;
ALTER TABLE consumable_moves ADD COLUMN unit_cost DECIMAL(14,4);
ALTER TABLE consumable_moves ADD COLUMN warehouse_id INTEGER;
ALTER TABLE consumable_moves ADD COLUMN move_id INTEGER;
CREATE INDEX IF NOT EXISTS idx_consumable_moves_wo ON consumable_moves(maintenance_id);
CREATE INDEX IF NOT EXISTS idx_consumable_moves_asset ON consumable_moves(asset_id);

-- from 121_hr_names_letters.sql
ALTER TABLE employees ADD COLUMN name_first VARCHAR(40);
ALTER TABLE employees ADD COLUMN name_father VARCHAR(40);
ALTER TABLE employees ADD COLUMN name_grand VARCHAR(40);
ALTER TABLE employees ADD COLUMN name_family VARCHAR(40);
ALTER TABLE employees ADD COLUMN name_first_ar VARCHAR(40);
ALTER TABLE employees ADD COLUMN name_father_ar VARCHAR(40);
ALTER TABLE employees ADD COLUMN name_grand_ar VARCHAR(40);
ALTER TABLE employees ADD COLUMN name_family_ar VARCHAR(40);

-- from 127_custody_swap.sql
ALTER TABLE custody_receipts ADD COLUMN ticket_id INTEGER;
CREATE INDEX IF NOT EXISTS idx_custody_ticket ON custody_receipts(ticket_id);

-- from 138_job_title_arabic.sql
ALTER TABLE employees ADD COLUMN title_ar VARCHAR(120);

-- built-in roles
INSERT INTO roles (slug, sort, permissions, created_at, updated_at) VALUES
('admin', 1, '["*"]', '2026-01-01 00:00:00', '2026-01-01 00:00:00'),
('manager', 2, '["assets.edit","assets.delete","assets.assign","maintenance.edit","licenses.edit","audits.run","catalog.edit","ledger.view","reports.export"]', '2026-01-01 00:00:00', '2026-01-01 00:00:00'),
('technician', 3, '["assets.edit","assets.assign","maintenance.edit","licenses.edit","audits.run","reports.export"]', '2026-01-01 00:00:00', '2026-01-01 00:00:00'),
('auditor', 4, '["audits.run","ledger.view","reports.export"]', '2026-01-01 00:00:00', '2026-01-01 00:00:00');
