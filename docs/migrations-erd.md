# GTIMS Migration ERD

This document summarizes the database structure defined by the Laravel migrations in `database/migrations`, including later `Schema::table(...)` updates that change the final schema.

Notes:
- This reflects the final intended schema after the alter migrations, not just the original `create_*` files.
- Temporary swap tables used during the supplier migration (`supplier_products_new`, `supplier_products_old`) are intentionally excluded.
- Some relationships are shown as `indexed only` because the migrations use `*_id` columns without creating a database foreign-key constraint.
- `notifications` and `audit_events` include polymorphic-style references that are not normal foreign keys.

## 1. Inventory and Supply

```mermaid
erDiagram
    USERS {
        bigint id PK
        string name
        string email
    }

    BRANCHES {
        bigint id PK
        string name
        string code
        boolean is_main
        boolean is_archived
        bigint archived_by FK
        datetime archived_at
    }

    PRODUCTS {
        bigint id PK
        string brand_name
        string generic_name
        string form
        string strength
        boolean is_archived
    }

    INVENTORIES {
        bigint id PK
        bigint product_id
        bigint branch_id
        string batch_number
        int quantity
        int onhand_qty
        int hold_qty
        date expiry_date
        boolean is_archived
    }

    PRODUCT_MOVEMENTS {
        bigint id PK
        bigint product_id FK
        bigint inventory_id FK
        bigint user_id FK
        string type
        int quantity
        int quantity_before
        int quantity_after
        string description
    }

    SUPPLIERS {
        bigint id PK
        string name
        string contact_person
        string email
        string phone
        boolean is_active
    }

    SUPPLIER_PRODUCTS {
        bigint id PK
        bigint supplier_id FK
        bigint inventory_id FK
        int lead_time_days
        decimal unit_cost
    }

    LOW_STOCK_SETTINGS {
        bigint id PK
        bigint product_id FK
        bigint branch_id FK
        int threshold
        boolean is_global
    }

    REORDER_RULES {
        bigint id PK
        bigint product_id FK
        bigint branch_id FK
        bigint preferred_supplier_id FK
        int reorder_point
        int reorder_quantity
    }

    PRODUCT_SUBSTITUTES {
        bigint id PK
        bigint product_id FK
        bigint substitute_product_id FK
        int priority
    }

    USERS o|--o{ BRANCHES : archives
    BRANCHES ||--o{ INVENTORIES : branch_id indexed only
    PRODUCTS ||--o{ INVENTORIES : product_id indexed only
    PRODUCTS ||--o{ PRODUCT_MOVEMENTS : tracks
    INVENTORIES ||--o{ PRODUCT_MOVEMENTS : batch movement
    USERS o|--o{ PRODUCT_MOVEMENTS : performed by
    SUPPLIERS ||--o{ SUPPLIER_PRODUCTS : offers
    INVENTORIES ||--o{ SUPPLIER_PRODUCTS : supplier link
    PRODUCTS o|--o{ LOW_STOCK_SETTINGS : threshold for
    BRANCHES o|--o{ LOW_STOCK_SETTINGS : branch scope
    PRODUCTS ||--o{ REORDER_RULES : reorder for
    BRANCHES o|--o{ REORDER_RULES : branch scope
    SUPPLIERS o|--o{ REORDER_RULES : preferred supplier
    PRODUCTS ||--o{ PRODUCT_SUBSTITUTES : primary product
    PRODUCTS ||--o{ PRODUCT_SUBSTITUTES : substitute product
```

## 2. Dispensing, Orders, Holds, and Requests

```mermaid
erDiagram
    USERS {
        bigint id PK
        string name
        string email
    }

    BRANCHES {
        bigint id PK
        string name
        string code
    }

    BARANGAYS {
        bigint id PK
        string barangay_name
    }

    PRODUCTS {
        bigint id PK
        string brand_name
        string generic_name
    }

    INVENTORIES {
        bigint id PK
        bigint product_id
        bigint branch_id
        string batch_number
    }

    PATIENTRECORDS {
        bigint id PK
        bigint branch_id FK
        bigint barangay_id
        string patient_name
        string purok
        string category
        date date_dispensed
    }

    DISPENSEDMEDICATIONS {
        bigint id PK
        bigint patientrecord_id FK
        bigint barangay_id
        string batch_number
        string generic_name
        string brand_name
        int quantity
    }

    ORDERS {
        bigint id PK
        bigint branch_id FK
        bigint user_id FK
        string status
        datetime admin_approved_at
        datetime finance_approved_at
    }

    ORDER_ITEMS {
        bigint id PK
        bigint order_id FK
        bigint product_id FK
        bigint source_branch_id FK
        bigint source_inventory_id FK
        string source_batch_number
        int quantity_requested
    }

    HOLDS {
        bigint id PK
        bigint branch_id FK
        bigint barangay_id FK
        bigint created_by FK
        bigint approved_by FK
        string type
        string reason_code
        string status
        datetime expires_at
    }

    HOLD_ITEMS {
        bigint id PK
        bigint hold_id FK
        bigint product_id FK
        bigint inventory_id FK
        int quantity
    }

    HOLD_STATUS_HISTORY {
        bigint id PK
        bigint hold_id FK
        bigint changed_by FK
        string old_status
        string new_status
    }

    INCOMING_REQUESTS {
        bigint id PK
        bigint branch_id FK
        bigint requester_id FK
        string department
        string priority
        string status
    }

    REQUEST_ITEMS {
        bigint id PK
        bigint incoming_request_id FK
        bigint product_id FK
        bigint substituted_product_id FK
        int quantity_requested
        int quantity_fulfilled
    }

    REQUEST_COMMENTS {
        bigint id PK
        bigint incoming_request_id FK
        bigint user_id FK
        text comment
    }

    REQUEST_ATTACHMENTS {
        bigint id PK
        bigint incoming_request_id FK
        bigint user_id FK
        string filename
        string original_name
        string mime_type
        bigint size
    }

    REQUEST_STATUS_HISTORY {
        bigint id PK
        bigint incoming_request_id FK
        bigint changed_by FK
        string old_status
        string new_status
    }

    BRANCHES ||--o{ PATIENTRECORDS : recorded at
    BARANGAYS ||--o{ PATIENTRECORDS : barangay_id indexed only
    PATIENTRECORDS ||--o{ DISPENSEDMEDICATIONS : dispenses
    BARANGAYS ||--o{ DISPENSEDMEDICATIONS : barangay_id indexed only
    BRANCHES ||--o{ ORDERS : created for
    USERS ||--o{ ORDERS : created by
    ORDERS ||--o{ ORDER_ITEMS : contains
    PRODUCTS ||--o{ ORDER_ITEMS : requested product
    BRANCHES o|--o{ ORDER_ITEMS : source branch
    INVENTORIES o|--o{ ORDER_ITEMS : source inventory
    BRANCHES ||--o{ HOLDS : branch hold
    BARANGAYS o|--o{ HOLDS : barangay hold
    USERS ||--o{ HOLDS : created or approved by
    HOLDS ||--o{ HOLD_ITEMS : contains
    PRODUCTS ||--o{ HOLD_ITEMS : held product
    INVENTORIES ||--o{ HOLD_ITEMS : held batch
    HOLDS ||--o{ HOLD_STATUS_HISTORY : status changes
    USERS ||--o{ HOLD_STATUS_HISTORY : changed by
    BRANCHES ||--o{ INCOMING_REQUESTS : requested from
    USERS ||--o{ INCOMING_REQUESTS : requester
    INCOMING_REQUESTS ||--o{ REQUEST_ITEMS : contains
    PRODUCTS ||--o{ REQUEST_ITEMS : requested product
    PRODUCTS o|--o{ REQUEST_ITEMS : substituted product
    INCOMING_REQUESTS ||--o{ REQUEST_COMMENTS : has comments
    USERS ||--o{ REQUEST_COMMENTS : wrote
    INCOMING_REQUESTS ||--o{ REQUEST_ATTACHMENTS : has files
    USERS ||--o{ REQUEST_ATTACHMENTS : uploaded by
    INCOMING_REQUESTS ||--o{ REQUEST_STATUS_HISTORY : status changes
    USERS ||--o{ REQUEST_STATUS_HISTORY : changed by
```

## 3. Users, Roles, Permissions, and Notification Context

```mermaid
erDiagram
    BRANCHES {
        bigint id PK
        string name
        string code
    }

    USERS {
        bigint id PK
        bigint branch_id FK
        bigint user_level_id FK
        string name
        string email
        string otp
        datetime otp_expires_at
        datetime last_login_at
        string last_login_ip
        boolean uses_custom_permissions
    }

    USER_LEVELS {
        bigint id PK
        string name
    }

    PERMISSIONS {
        bigint id PK
        string name
        string group
        string description
    }

    ROLE_PERMISSIONS {
        bigint id PK
        bigint user_level_id FK
        bigint permission_id FK
    }

    USER_PERMISSIONS {
        bigint id PK
        bigint user_id FK
        bigint permission_id FK
    }

    NOTIFICATION_PREFERENCES {
        bigint id PK
        bigint user_id FK
        string type
        boolean email_enabled
        boolean in_app_enabled
    }

    IDEMPOTENCY_KEYS {
        bigint id PK
        bigint user_id FK
        string key
        string action
    }

    AUDIT_EVENTS {
        bigint id PK
        bigint user_id FK
        string action
        string entity_type
        bigint entity_id
        text reason
    }

    NOTIFICATIONS {
        uuid id PK
        string notifiable_type
        bigint notifiable_id
        string type
        datetime read_at
    }

    BRANCHES o|--o{ USERS : assigned branch
    USER_LEVELS o|--o{ USERS : assigned level
    USER_LEVELS ||--o{ ROLE_PERMISSIONS : grants
    PERMISSIONS ||--o{ ROLE_PERMISSIONS : role mapping
    USERS ||--o{ USER_PERMISSIONS : custom grants
    PERMISSIONS ||--o{ USER_PERMISSIONS : custom mapping
    USERS ||--o{ NOTIFICATION_PREFERENCES : preferences
    USERS ||--o{ IDEMPOTENCY_KEYS : action keys
    USERS ||--o{ AUDIT_EVENTS : actor
```

## 4. Workflow and Branch Lifecycle

```mermaid
erDiagram
    USERS {
        bigint id PK
        string name
        string email
    }

    BRANCHES {
        bigint id PK
        string name
        string code
    }

    BRANCH_ARCHIVAL_RUNS {
        bigint id PK
        bigint source_branch_id FK
        bigint target_branch_id FK
        bigint initiated_by FK
        string status
        int progress_percent
        datetime started_at
        datetime completed_at
    }

    WORKFLOW_DEFINITIONS {
        bigint id PK
        bigint created_by FK
        bigint updated_by FK
        bigint branch_id FK
        string name
        string status
        int current_version
        int max_concurrency
        json webhook_allowlist
        string webhook_secret
        datetime deleted_at
    }

    WORKFLOW_VERSIONS {
        bigint id PK
        bigint workflow_definition_id FK
        bigint published_by FK
        int version_number
        string status
        datetime published_at
    }

    WORKFLOW_NODES {
        bigint id PK
        bigint workflow_version_id FK
        string node_id
        string type
        string action_type
        string label
    }

    WORKFLOW_EDGES {
        bigint id PK
        bigint workflow_version_id FK
        string source_node_id
        string target_node_id
        string label
        string condition_branch
    }

    WORKFLOW_RUNS {
        bigint id PK
        bigint workflow_definition_id FK
        bigint workflow_version_id FK
        bigint triggered_by FK
        bigint parent_run_id FK
        string status
        string trigger_type
        boolean is_dry_run
        int retry_attempt
        int max_retries
        boolean is_dead_letter
        string idempotency_key
    }

    WORKFLOW_RUN_STEPS {
        bigint id PK
        bigint workflow_run_id FK
        string node_id
        string action_type
        string status
        int retry_count
        int max_retries
        datetime next_retry_at
    }

    WORKFLOW_PERMISSIONS {
        bigint id PK
        bigint workflow_definition_id FK
        bigint user_id FK
        string permission
    }

    BRANCHES ||--o{ BRANCH_ARCHIVAL_RUNS : source branch
    BRANCHES ||--o{ BRANCH_ARCHIVAL_RUNS : target branch
    USERS ||--o{ BRANCH_ARCHIVAL_RUNS : initiated by
    BRANCHES o|--o{ WORKFLOW_DEFINITIONS : branch scope
    USERS ||--o{ WORKFLOW_DEFINITIONS : created or updated by
    WORKFLOW_DEFINITIONS ||--o{ WORKFLOW_VERSIONS : has versions
    USERS o|--o{ WORKFLOW_VERSIONS : published by
    WORKFLOW_VERSIONS ||--o{ WORKFLOW_NODES : has nodes
    WORKFLOW_VERSIONS ||--o{ WORKFLOW_EDGES : has edges
    WORKFLOW_DEFINITIONS ||--o{ WORKFLOW_RUNS : executed as
    WORKFLOW_VERSIONS ||--o{ WORKFLOW_RUNS : run version
    USERS o|--o{ WORKFLOW_RUNS : triggered by
    WORKFLOW_RUNS o|--o{ WORKFLOW_RUNS : parent run
    WORKFLOW_RUNS ||--o{ WORKFLOW_RUN_STEPS : has steps
    WORKFLOW_DEFINITIONS ||--o{ WORKFLOW_PERMISSIONS : permission set
    USERS ||--o{ WORKFLOW_PERMISSIONS : assignee
```

## 5. Framework and Support Tables

```mermaid
erDiagram
    USERS {
        bigint id PK
        string name
        string email
    }

    PASSWORD_RESET_TOKENS {
        string email PK
        string token
        datetime created_at
    }

    SESSIONS {
        string id PK
        bigint user_id
        string ip_address
        int last_activity
    }

    CACHE {
        string key PK
        int expiration
    }

    CACHE_LOCKS {
        string key PK
        string owner
        int expiration
    }

    JOBS {
        bigint id PK
        string queue
        tinyint attempts
        int available_at
        int created_at
    }

    JOB_BATCHES {
        string id PK
        string name
        int total_jobs
        int pending_jobs
        int failed_jobs
    }

    FAILED_JOBS {
        bigint id PK
        string uuid
        string queue
        datetime failed_at
    }

    HISTORY_LOGS {
        bigint id PK
        string action
        bigint user_id
        string user_name
        json metadata
    }

    USERS o|--o{ SESSIONS : user_id indexed only
    USERS o|--o{ HISTORY_LOGS : user_id indexed only
```

## Caveats and Assumptions

- `inventories.product_id` and `inventories.branch_id` are important logical relationships, but the migrations only index them and do not declare `constrained(...)`.
- `patientrecords.barangay_id` and `dispensedmedications.barangay_id` are also index-only references in the migrations.
- `sessions.user_id` and `history_logs.user_id` look like user references but are not declared as foreign keys.
- `notifications` uses Laravel's polymorphic `morphs('notifiable')`, so the target entity depends on `notifiable_type`.
- `audit_events.entity_type` plus `entity_id` is a polymorphic audit target, not a fixed FK.
- `workflow_edges.source_node_id` and `workflow_edges.target_node_id` refer to `workflow_nodes.node_id` values inside the same workflow version, but the database does not enforce those references with foreign keys.
