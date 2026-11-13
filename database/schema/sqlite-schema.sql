CREATE TABLE IF NOT EXISTS "migrations"(
  "id" integer primary key autoincrement not null,
  "migration" varchar not null,
  "batch" integer not null
);
CREATE TABLE IF NOT EXISTS "cache"(
  "key" varchar not null,
  "value" text not null,
  "expiration" integer not null,
  primary key("key")
);
CREATE INDEX "cache_expiration_index" on "cache"("expiration");
CREATE TABLE IF NOT EXISTS "cache_locks"(
  "key" varchar not null,
  "owner" varchar not null,
  "expiration" integer not null,
  primary key("key")
);
CREATE INDEX "cache_locks_expiration_index" on "cache_locks"("expiration");
CREATE TABLE IF NOT EXISTS "jobs"(
  "id" integer primary key autoincrement not null,
  "queue" varchar not null,
  "payload" text not null,
  "attempts" integer not null,
  "reserved_at" integer,
  "available_at" integer not null,
  "created_at" integer not null
);
CREATE INDEX "jobs_queue_index" on "jobs"("queue");
CREATE TABLE IF NOT EXISTS "job_batches"(
  "id" varchar not null,
  "name" varchar not null,
  "total_jobs" integer not null,
  "pending_jobs" integer not null,
  "failed_jobs" integer not null,
  "failed_job_ids" text not null,
  "options" text,
  "cancelled_at" integer,
  "created_at" integer not null,
  "finished_at" integer,
  primary key("id")
);
CREATE TABLE IF NOT EXISTS "failed_jobs"(
  "id" integer primary key autoincrement not null,
  "uuid" varchar not null,
  "connection" varchar not null,
  "queue" varchar not null,
  "payload" text not null,
  "exception" text not null,
  "failed_at" datetime not null default CURRENT_TIMESTAMP
);
CREATE INDEX "failed_jobs_connection_queue_failed_at_index" on "failed_jobs"(
  "connection",
  "queue",
  "failed_at"
);
CREATE UNIQUE INDEX "failed_jobs_uuid_unique" on "failed_jobs"("uuid");
CREATE TABLE IF NOT EXISTS "business_settings"(
  "id" integer primary key autoincrement not null,
  "name" varchar not null,
  "settings" text not null,
  "created_at" datetime,
  "updated_at" datetime
);
CREATE TABLE IF NOT EXISTS "sessions"(
  "id" varchar not null,
  "user_id" integer,
  "ip_address" varchar,
  "user_agent" text,
  "payload" text not null,
  "last_activity" integer not null,
  primary key("id")
);
CREATE INDEX "sessions_user_id_index" on "sessions"("user_id");
CREATE INDEX "sessions_last_activity_index" on "sessions"("last_activity");
CREATE TABLE IF NOT EXISTS "password_reset_tokens"(
  "email" varchar not null,
  "token" varchar not null,
  "created_at" datetime,
  primary key("email")
);
CREATE TABLE IF NOT EXISTS "branches"(
  "id" integer primary key autoincrement not null,
  "code" varchar not null,
  "name" varchar not null,
  "name_ar" varchar,
  "address" text,
  "is_active" tinyint(1) not null default '1',
  "settings" text,
  "created_at" datetime,
  "updated_at" datetime
);
CREATE UNIQUE INDEX "branches_code_unique" on "branches"("code");
CREATE TABLE IF NOT EXISTS "staff_users"(
  "id" integer primary key autoincrement not null,
  "branch_id" integer,
  "name" varchar not null,
  "email" varchar not null,
  "email_verified_at" datetime,
  "password" varchar not null,
  "is_active" tinyint(1) not null default '1',
  "remember_token" varchar,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("branch_id") references "branches"("id") on delete set null
);
CREATE UNIQUE INDEX "staff_users_email_unique" on "staff_users"("email");
CREATE TABLE IF NOT EXISTS "customers"(
  "id" integer primary key autoincrement not null,
  "name" varchar not null,
  "phone" varchar,
  "email" varchar,
  "vat_number" varchar,
  "referral_code" varchar,
  "referred_by_customer_id" integer,
  "points_balance" integer not null default '0',
  "profile" text,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("referred_by_customer_id") references "customers"("id") on delete set null
);
CREATE UNIQUE INDEX "customers_phone_unique" on "customers"("phone");
CREATE UNIQUE INDEX "customers_referral_code_unique" on "customers"(
  "referral_code"
);
CREATE TABLE IF NOT EXISTS "product_categories"(
  "id" integer primary key autoincrement not null,
  "branch_id" integer,
  "name_en" varchar not null,
  "name_ar" varchar,
  "name_bn" varchar,
  "icon_key" varchar,
  "description_en" text,
  "description_ar" text,
  "description_bn" text,
  "sort_order" integer not null default '0',
  "is_active" tinyint(1) not null default '1',
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("branch_id") references "branches"("id") on delete set null
);
CREATE INDEX "product_categories_branch_id_is_active_index" on "product_categories"(
  "branch_id",
  "is_active"
);
CREATE TABLE IF NOT EXISTS "products"(
  "id" integer primary key autoincrement not null,
  "branch_id" integer,
  "product_category_id" integer,
  "sku" varchar,
  "item_type" varchar not null default 'item',
  "name_en" varchar not null,
  "name_ar" varchar,
  "name_bn" varchar,
  "image_url" varchar,
  "description_en" text,
  "description_ar" text,
  "description_bn" text,
  "price_ex_vat" numeric not null,
  "vat_rate" numeric not null default '15',
  "sort_order" integer not null default '0',
  "is_active" tinyint(1) not null default '1',
  "show_on_homepage" tinyint(1) not null default '0',
  "show_on_menu" tinyint(1) not null default '1',
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("branch_id") references "branches"("id") on delete set null,
  foreign key("product_category_id") references "product_categories"("id") on delete set null
);
CREATE INDEX "products_branch_id_is_active_index" on "products"(
  "branch_id",
  "is_active"
);
CREATE INDEX "products_show_on_homepage_is_active_index" on "products"(
  "show_on_homepage",
  "is_active"
);
CREATE INDEX "products_show_on_menu_is_active_index" on "products"(
  "show_on_menu",
  "is_active"
);
CREATE TABLE IF NOT EXISTS "product_modifiers"(
  "id" integer primary key autoincrement not null,
  "name_en" varchar not null,
  "name_ar" varchar,
  "price_ex_vat" numeric not null default '0',
  "vat_rate" numeric not null default '15',
  "is_active" tinyint(1) not null default '1',
  "created_at" datetime,
  "updated_at" datetime
);
CREATE TABLE IF NOT EXISTS "product_product_modifier"(
  "product_id" integer not null,
  "product_modifier_id" integer not null,
  foreign key("product_id") references "products"("id") on delete cascade,
  foreign key("product_modifier_id") references "product_modifiers"("id") on delete cascade,
  primary key("product_id", "product_modifier_id")
);
CREATE TABLE IF NOT EXISTS "invoices"(
  "id" integer primary key autoincrement not null,
  "branch_id" integer,
  "document_number" varchar not null,
  "document_type" varchar not null,
  "status" varchar not null default 'draft',
  "referenced_invoice_id" integer,
  "source_type" varchar,
  "source_id" integer,
  "icv" integer,
  "invoice_hash" varchar,
  "previous_invoice_hash" varchar,
  "signed_xml" text,
  "qr_tlv_base64" text,
  "payload" text,
  "issued_at" datetime,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("branch_id") references "branches"("id") on delete set null,
  foreign key("referenced_invoice_id") references "invoices"("id") on delete set null
);
CREATE INDEX "invoices_source_type_source_id_index" on "invoices"(
  "source_type",
  "source_id"
);
CREATE INDEX "invoices_status_issued_at_index" on "invoices"(
  "status",
  "issued_at"
);
CREATE UNIQUE INDEX "invoices_document_number_unique" on "invoices"(
  "document_number"
);
CREATE TABLE IF NOT EXISTS "invoice_audit_logs"(
  "id" integer primary key autoincrement not null,
  "invoice_id" integer not null,
  "event" varchar not null,
  "context" text,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("invoice_id") references "invoices"("id") on delete cascade
);
CREATE TABLE IF NOT EXISTS "pos_orders"(
  "id" integer primary key autoincrement not null,
  "branch_id" integer not null,
  "customer_id" integer,
  "cashier_id" integer,
  "order_number" varchar not null,
  "status" varchar not null default 'open',
  "payment_method" varchar,
  "subtotal_ex_vat" numeric not null default '0',
  "discount_ex_vat" numeric not null default '0',
  "promo_discount_ex_vat" numeric not null default '0',
  "loyalty_discount_ex_vat" numeric not null default '0',
  "vat_total" numeric not null default '0',
  "total_inc_vat" numeric not null default '0',
  "loyalty_points_redeemed" integer not null default '0',
  "line_items" text not null,
  "metadata" text,
  "invoice_id" integer,
  "refund_credit_note_id" integer,
  "paid_at" datetime,
  "cancelled_at" datetime,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("branch_id") references "branches"("id"),
  foreign key("customer_id") references "customers"("id") on delete set null,
  foreign key("cashier_id") references "staff_users"("id") on delete set null,
  foreign key("invoice_id") references "invoices"("id") on delete set null,
  foreign key("refund_credit_note_id") references "invoices"("id") on delete set null
);
CREATE INDEX "pos_orders_branch_id_status_index" on "pos_orders"(
  "branch_id",
  "status"
);
CREATE UNIQUE INDEX "pos_orders_order_number_unique" on "pos_orders"(
  "order_number"
);
CREATE TABLE IF NOT EXISTS "loyalty_transactions"(
  "id" integer primary key autoincrement not null,
  "customer_id" integer not null,
  "pos_order_id" integer,
  "type" varchar not null,
  "points_delta" integer not null,
  "monetary_value_ex_vat" numeric,
  "metadata" text,
  "occurred_at" datetime not null,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("customer_id") references "customers"("id") on delete cascade,
  foreign key("pos_order_id") references "pos_orders"("id") on delete set null
);
CREATE TABLE IF NOT EXISTS "ingredients"(
  "id" integer primary key autoincrement not null,
  "branch_id" integer,
  "name_en" varchar not null,
  "name_ar" varchar,
  "name_bn" varchar,
  "unit" varchar not null default 'g',
  "quantity_on_hand" numeric not null default '0',
  "reorder_level" numeric not null default '0',
  "low_stock_threshold" numeric not null default '0',
  "is_active" tinyint(1) not null default '1',
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("branch_id") references "branches"("id") on delete set null
);
CREATE TABLE IF NOT EXISTS "inventory_movements"(
  "id" integer primary key autoincrement not null,
  "branch_id" integer not null,
  "ingredient_id" integer not null,
  "type" varchar not null,
  "reason" varchar,
  "quantity_delta" numeric not null,
  "reference_type" varchar,
  "reference_id" integer,
  "user_id" integer,
  "pos_order_id" integer,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("branch_id") references "branches"("id") on delete cascade,
  foreign key("ingredient_id") references "ingredients"("id") on delete cascade,
  foreign key("user_id") references "staff_users"("id") on delete set null,
  foreign key("pos_order_id") references "pos_orders"("id") on delete set null
);
CREATE TABLE IF NOT EXISTS "recipes"(
  "id" integer primary key autoincrement not null,
  "product_id" integer not null,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("product_id") references "products"("id") on delete cascade
);
CREATE UNIQUE INDEX "recipes_product_id_unique" on "recipes"("product_id");
CREATE TABLE IF NOT EXISTS "recipe_ingredients"(
  "id" integer primary key autoincrement not null,
  "recipe_id" integer not null,
  "ingredient_id" integer not null,
  "quantity" numeric not null,
  foreign key("recipe_id") references "recipes"("id") on delete cascade,
  foreign key("ingredient_id") references "ingredients"("id") on delete cascade
);
CREATE UNIQUE INDEX "recipe_ingredients_recipe_id_ingredient_id_unique" on "recipe_ingredients"(
  "recipe_id",
  "ingredient_id"
);
CREATE TABLE IF NOT EXISTS "product_ingredients"(
  "id" integer primary key autoincrement not null,
  "product_id" integer not null,
  "ingredient_id" integer not null,
  "quantity_per_unit" numeric not null,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("product_id") references "products"("id") on delete cascade,
  foreign key("ingredient_id") references "ingredients"("id") on delete cascade
);
CREATE UNIQUE INDEX "product_ingredients_product_id_ingredient_id_unique" on "product_ingredients"(
  "product_id",
  "ingredient_id"
);
CREATE TABLE IF NOT EXISTS "shifts"(
  "id" integer primary key autoincrement not null,
  "branch_id" integer not null,
  "staff_user_id" integer not null,
  "started_at" datetime not null,
  "opening_cash_float" numeric,
  "closing_cash_counted" numeric,
  "close_notes" text,
  "ended_at" datetime,
  "metadata" text,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("branch_id") references "branches"("id") on delete cascade,
  foreign key("staff_user_id") references "staff_users"("id") on delete cascade
);
CREATE TABLE IF NOT EXISTS "business_settings_audit_logs"(
  "id" integer primary key autoincrement not null,
  "staff_user_id" integer,
  "tab" varchar not null,
  "snapshot" text,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("staff_user_id") references "staff_users"("id") on delete set null
);
CREATE TABLE IF NOT EXISTS "permissions"(
  "id" integer primary key autoincrement not null,
  "name" varchar not null,
  "guard_name" varchar not null,
  "created_at" datetime,
  "updated_at" datetime
);
CREATE UNIQUE INDEX "permissions_name_guard_name_unique" on "permissions"(
  "name",
  "guard_name"
);
CREATE TABLE IF NOT EXISTS "roles"(
  "id" integer primary key autoincrement not null,
  "name" varchar not null,
  "guard_name" varchar not null,
  "created_at" datetime,
  "updated_at" datetime
);
CREATE UNIQUE INDEX "roles_name_guard_name_unique" on "roles"(
  "name",
  "guard_name"
);
CREATE TABLE IF NOT EXISTS "model_has_permissions"(
  "permission_id" integer not null,
  "model_type" varchar not null,
  "model_id" integer not null,
  foreign key("permission_id") references "permissions"("id") on delete cascade,
  primary key("permission_id", "model_id", "model_type")
);
CREATE INDEX "model_has_permissions_model_id_model_type_index" on "model_has_permissions"(
  "model_id",
  "model_type"
);
CREATE TABLE IF NOT EXISTS "model_has_roles"(
  "role_id" integer not null,
  "model_type" varchar not null,
  "model_id" integer not null,
  foreign key("role_id") references "roles"("id") on delete cascade,
  primary key("role_id", "model_id", "model_type")
);
CREATE INDEX "model_has_roles_model_id_model_type_index" on "model_has_roles"(
  "model_id",
  "model_type"
);
CREATE TABLE IF NOT EXISTS "role_has_permissions"(
  "permission_id" integer not null,
  "role_id" integer not null,
  foreign key("permission_id") references "permissions"("id") on delete cascade,
  foreign key("role_id") references "roles"("id") on delete cascade,
  primary key("permission_id", "role_id")
);

INSERT INTO migrations VALUES(1,'0001_01_01_000001_create_cache_table',1);
INSERT INTO migrations VALUES(2,'0001_01_01_000002_create_jobs_table',1);
INSERT INTO migrations VALUES(3,'0001_01_01_000010_single_client_application_schema',1);
INSERT INTO migrations VALUES(4,'0001_01_01_000020_create_permission_tables',1);
