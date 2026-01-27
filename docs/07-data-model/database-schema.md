# Data Model

**Document Set:** CRE8.pw Documentation v1.0.0
**Last Updated:** 2026-01-21
**Status:** Canonical (SSoT)

**Scope:** Complete database schema contract, entity definitions, identifier formats, lineage invariants, indexes, and migration ordering.

**SSoT Ownership:**
- Database schema for all entities
- Lineage fields and invariants
- Identifier storage formats (BINARY(16) internally, hex32 externally)
- Required indexes
- Migration ordering

---

## 1. Database Baseline

**Database:** MariaDB 11.4.x or higher
**Charset:** utf8mb4
**Collation:** utf8mb4_bin (binary, case-sensitive)
**Access:** PDO with prepared statements exclusively

---

## 2. ID Formats and Encoding

**Internal Storage:** `BINARY(16)` for all primary/foreign keys
**External Representation:** `hex32` (32-char lowercase hex)
**Key Public IDs:** `apub_...` format (stored separately in key_public_ids table)

**Conversion:** `Utilities/Ids.php` provides `binaryToHex32()` and `hex32ToBinary()` functions

**See:** **[identifier-encoding.md](../10-reference/identifier-encoding.md)** for complete rules

---

## 3. Entity Catalog

### owners

| Column | Type | Constraints | Description |
|---|---|---|---|
| id | BINARY(16) | PK | Owner ID |
| email | VARCHAR(255) | UNIQUE, NOT NULL | Owner email |
| password_hash | VARCHAR(255) | NOT NULL | Argon2id hash |
| created_at | TIMESTAMP | DEFAULT CURRENT_TIMESTAMP | |
| updated_at | TIMESTAMP | ON UPDATE CURRENT_TIMESTAMP | |

### keys

| Column | Type | Constraints | Description |
|---|---|---|---|
| id | BINARY(16) | PK | Key ID |
| type | ENUM('primary','secondary','use') | NOT NULL | Key type |
| key_secret_hash | VARCHAR(255) | NOT NULL | Argon2id hash of secret |
| permissions_json | JSON | NOT NULL | Permission strings array |
| active | BOOLEAN | DEFAULT 1 | Active status |
| issued_by_key_id | BINARY(16) | FK keys.id, NULL for primary | Issuing key |
| parent_key_id | BINARY(16) | FK keys.id, NULL for primary | Parent key |
| initial_author_key_id | BINARY(16) | FK keys.id, SELF for primary | Root author |
| rotated_from_id | BINARY(16) | FK keys.id, nullable | Previous key in rotation |
| rotated_to_id | BINARY(16) | FK keys.id, nullable | Next key in rotation |
| retired_at | TIMESTAMP | nullable | Retirement timestamp |
| use_count_limit | INT | nullable | Max uses for Use Keys |
| use_count_current | INT | DEFAULT 0 | Current use count |
| device_limit | INT | nullable | Max devices for Use Keys |
| created_at | TIMESTAMP | | |
| updated_at | TIMESTAMP | | |

**Lineage Invariants:**
- Primary: `issued_by_key_id = NULL`, `parent_key_id = NULL`, `initial_author_key_id = id` (self)
- Secondary/Use: All lineage fields NOT NULL

### key_public_ids

| Column | Type | Constraints | Description |
|---|---|---|---|
| id | BINARY(16) | PK | Record ID |
| key_id | BINARY(16) | FK keys.id, UNIQUE | Key ID |
| key_public_id | VARCHAR(64) | UNIQUE, NOT NULL | apub_... format |
| created_at | TIMESTAMP | | |

### posts

| Column | Type | Constraints | Description |
|---|---|---|---|
| id | BINARY(16) | PK | Post ID |
| author_key_id | BINARY(16) | FK keys.id, NOT NULL | Author |
| initial_author_key_id | BINARY(16) | FK keys.id, NOT NULL | Root author (provenance) |
| title | VARCHAR(255) | nullable | Optional title |
| content | TEXT | NOT NULL | Post content |
| created_at | TIMESTAMP | | |
| updated_at | TIMESTAMP | | |

### comments

| Column | Type | Constraints | Description |
|---|---|---|---|
| id | BINARY(16) | PK | Comment ID |
| post_id | BINARY(16) | FK posts.id, NOT NULL | Post |
| created_by_key_id | BINARY(16) | FK keys.id, NOT NULL | Commenter |
| body | TEXT | NOT NULL | Comment content |
| created_at | TIMESTAMP | | |

### post_access

| Column | Type | Constraints | Description |
|---|---|---|---|
| id | BINARY(16) | PK | Access grant ID |
| post_id | BINARY(16) | FK posts.id, NOT NULL | Post |
| target_type | ENUM('key','group') | NOT NULL | Target type |
| target_id | BINARY(16) | NOT NULL | Key or Group ID |
| permission_mask | INT | NOT NULL | Bitmask (VIEW/COMMENT/MANAGE_ACCESS) |
| created_at | TIMESTAMP | | |

**Composite Index:** `(post_id, target_type, target_id)` UNIQUE

### groups

| Column | Type | Constraints | Description |
|---|---|---|---|
| id | BINARY(16) | PK | Group ID |
| owner_id | BINARY(16) | FK owners.id, NOT NULL | Owner |
| name | VARCHAR(255) | NOT NULL | Group name |
| created_at | TIMESTAMP | | |
| updated_at | TIMESTAMP | | |

### group_members

| Column | Type | Constraints | Description |
|---|---|---|---|
| group_id | BINARY(16) | FK groups.id, NOT NULL | Group |
| key_id | BINARY(16) | FK keys.id, NOT NULL | Member key |
| created_at | TIMESTAMP | | |

**Composite PK:** `(group_id, key_id)`

### keychains

| Column | Type | Constraints | Description |
|---|---|---|---|
| id | BINARY(16) | PK | Keychain ID |
| name | VARCHAR(255) | NOT NULL | Keychain name |
| owner_id | BINARY(16) | FK owners.id, nullable | Owner (null = external) |
| created_at | TIMESTAMP | | |
| updated_at | TIMESTAMP | | |

### keychain_members

| Column | Type | Constraints | Description |
|---|---|---|---|
| keychain_id | BINARY(16) | FK keychains.id, NOT NULL | Keychain |
| key_id | BINARY(16) | FK keys.id, NOT NULL | Member key |
| created_at | TIMESTAMP | | |

**Composite PK:** `(keychain_id, key_id)`

### refresh_tokens

| Column | Type | Constraints | Description |
|---|---|---|---|
| id | BINARY(16) | PK | Token ID |
| subject_type | ENUM('owner','key') | NOT NULL | Principal type |
| subject_id | BINARY(16) | NOT NULL | Owner or Key ID |
| token_hash | VARCHAR(255) | NOT NULL | Argon2id hash |
| issued_at | TIMESTAMP | | |
| expires_at | TIMESTAMP | | |
| revoked_at | TIMESTAMP | nullable | Revocation timestamp |
| rotated_at | TIMESTAMP | nullable | Rotation timestamp |
| replaced_by_id | BINARY(16) | FK refresh_tokens.id, nullable | Replacement token |
| ip | VARCHAR(45) | nullable | Client IP |
| user_agent | VARCHAR(255) | nullable | Client UA |

**Index:** `(token_hash)`, `(subject_type, subject_id)`

### audit_events

| Column | Type | Constraints | Description |
|---|---|---|---|
| id | BINARY(16) | PK | Event ID |
| actor_type | ENUM('owner','key') | NOT NULL | Actor type |
| actor_id | BINARY(16) | NOT NULL | Actor ID |
| action | VARCHAR(100) | NOT NULL | Action (e.g., keys:mint) |
| subject_type | VARCHAR(50) | nullable | Subject type |
| subject_id | BINARY(16) | nullable | Subject ID |
| metadata_json | JSON | nullable | Additional metadata |
| ip | VARCHAR(45) | nullable | Client IP |
| user_agent | VARCHAR(255) | nullable | Client UA |
| created_at | TIMESTAMP | | |

**Index:** `(actor_type, actor_id, created_at)`, `(subject_type, subject_id)`

---

## 4. Migration Ordering

Migrations must be executed in this order:

1. `001_create_owners.php`
2. `002_create_keys.php`
3. `003_create_posts_and_comments.php`
4. `004_create_groups.php`
5. `005_create_keychains.php`
6. `006_create_tokens.php`
7. `007_create_audit_events.php`
8. `008_indexes.php`
9. `009_foreign_keys.php` (if not inline)

---

## 5. Critical Indexes

```sql
-- keys
CREATE INDEX idx_keys_type ON keys(type);
CREATE INDEX idx_keys_active ON keys(active);
CREATE INDEX idx_keys_lineage ON keys(initial_author_key_id);

-- posts
CREATE INDEX idx_posts_author ON posts(author_key_id);
CREATE INDEX idx_posts_created ON posts(created_at DESC);

-- post_access
CREATE UNIQUE INDEX idx_post_access_unique ON post_access(post_id, target_type, target_id);

-- group_members
PRIMARY KEY (group_id, key_id)

-- refresh_tokens
CREATE INDEX idx_refresh_token_hash ON refresh_tokens(token_hash);
CREATE INDEX idx_refresh_subject ON refresh_tokens(subject_type, subject_id);

-- audit_events
CREATE INDEX idx_audit_actor ON audit_events(actor_type, actor_id, created_at);
```

---

**Next:** **[key-lifecycle.md](../03-core-concepts/key-lifecycle.md)**
