# CRE8.pw Installation and Setup Guide

**Version:** 1.0.0  
**Last Updated:** 2026-01-23  
**Status:** Complete Installation Guide

---

## Table of Contents

1. [Prerequisites](#1-prerequisites)
2. [Initial Setup](#2-initial-setup)
3. [Database Configuration](#3-database-configuration)
4. [JWT Key Generation](#4-jwt-key-generation)
5. [Environment Configuration](#5-environment-configuration)
6. [Database Migrations](#6-database-migrations)
7. [Starting the Application](#7-starting-the-application)
8. [Verifying Installation](#8-verifying-installation)
9. [Registering Your First Owner](#9-registering-your-first-owner)
10. [Creating Your First Primary Author Key](#10-creating-your-first-primary-author-key)
11. [Creating Your First Post](#11-creating-your-first-post)
12. [Sharing Content with Others](#12-sharing-content-with-others)
13. [Troubleshooting](#13-troubleshooting)
14. [Quick Reference](#quick-reference)
15. [Next Steps](#next-steps)

---

## 1. Prerequisites

Before installing CRE8.pw, ensure you have the following installed on your system:

### 1.1 Required Software

- **PHP 8.3 or higher**
  - Check version: `php -v`
  - Required extensions:
    - `pdo` (for database access)
    - `pdo_mysql` (for MariaDB/MySQL)
    - `sodium` (for Argon2id password hashing)
    - `openssl` (for JWT key generation)
    - `json` (usually included)
    - `mbstring` (usually included)

- **Composer** (PHP dependency manager)
  - Check installation: `composer --version`
  - Download from: https://getcomposer.org/

- **MariaDB 11.4.x or MySQL 8.0+**
  - Check version: `mysql --version` or `mariadb --version`
  - Must support `utf8mb4` charset and `utf8mb4_bin` collation

- **OpenSSL** (for generating JWT keys)
  - Check installation: `openssl version`
  - Usually pre-installed on Linux/macOS
  - Windows: Included with Git for Windows or install separately

### 1.2 System Requirements

- **Operating System:** Linux, macOS, or Windows (with WSL recommended for Windows)
- **Memory:** Minimum 512MB RAM (1GB+ recommended)
- **Disk Space:** Minimum 100MB for application files
- **Network:** Port 8000 (or your chosen port) available for local development

### 1.3 Verify Prerequisites

Run these commands to verify your environment:

```bash
# Check PHP version (must be 8.3+)
php -v

# Check required PHP extensions
php -m | grep -E "(pdo|pdo_mysql|sodium|openssl|json|mbstring)"

# Check Composer
composer --version

# Check MariaDB/MySQL
mysql --version

# Check OpenSSL
openssl version
```

If any of these commands fail, install the missing component before proceeding.

---

## 2. Initial Setup

### 2.1 Clone or Download the Application

If you have the application source code:

```bash
# Navigate to your desired installation directory
cd /path/to/your/projects

# If using git (if repository is available)
git clone <repository-url> cre8.pw
cd cre8.pw

# Or if you have the files already, navigate to the directory
cd /path/to/cre8.pw
```

### 2.2 Install PHP Dependencies

Navigate to the application root directory and install dependencies using Composer:

```bash
# Ensure you're in the application root directory
# (should contain composer.json file)
pwd

# Install dependencies
composer install
```

**Expected Output:**
```
Loading composer repositories with package information
Installing dependencies (including require-dev) from lock file
...
Package operations: X install(s), Y update(s), Z removals
...
```

**If you encounter errors:**
- Ensure PHP 8.3+ is installed
- Ensure all required PHP extensions are enabled
- Check your internet connection (Composer downloads packages)
- Try `composer update` if `composer install` fails

### 2.3 Verify Installation

After Composer completes, verify the installation:

```bash
# Check that vendor directory exists
ls -la vendor/

# Check that autoload file exists
ls -la vendor/autoload.php

# Test PHP can load the application
php -r "require 'vendor/autoload.php'; echo 'Autoload successful\n';"
```

---

## 3. Database Configuration

### 3.1 Create Database

Connect to your MariaDB/MySQL server and create a database:

```bash
# Connect to MariaDB/MySQL as root or admin user
mysql -u root -p

# Or if using MariaDB directly
mariadb -u root -p
```

Once connected, run these SQL commands:

```sql
-- Create the database
CREATE DATABASE cre8pw CHARACTER SET utf8mb4 COLLATE utf8mb4_bin;

-- Create a dedicated user (recommended for security)
CREATE USER 'cre8_user'@'localhost' IDENTIFIED BY 'your_secure_password_here';

-- Grant privileges
GRANT ALL PRIVILEGES ON cre8pw.* TO 'cre8_user'@'localhost';

-- Apply changes
FLUSH PRIVILEGES;

-- Verify database was created
SHOW DATABASES LIKE 'cre8pw';

-- Exit MySQL/MariaDB
EXIT;
```

**Important Notes:**
- Replace `your_secure_password_here` with a strong password
- For production, use a more restrictive user with only necessary privileges
- The database name `cre8pw` can be changed, but update `.env` accordingly

### 3.2 Verify Database Connection

Test the database connection:

```bash
# Test connection with the new user
mysql -u cre8_user -p cre8pw

# If connection succeeds, you'll see:
# MariaDB [cre8pw]>

# Exit
EXIT;
```

---

## 4. JWT Key Generation

CRE8.pw uses RS256 JWT tokens, which require a public/private key pair.

### 4.1 Create Keys Directory

```bash
# Navigate to application root
cd /path/to/cre8.pw

# Create keys directory
mkdir -p keys

# Set appropriate permissions (private key should be readable only by owner)
chmod 700 keys
```

### 4.2 Generate Private Key

```bash
# Generate 2048-bit RSA private key
openssl genrsa -out keys/private.pem 2048

# Verify key was created
ls -la keys/private.pem

# Set restrictive permissions (owner read/write only)
chmod 600 keys/private.pem
```

**Expected Output:**
```
Generating RSA private key, 2048 bit long modulus
...
e is 65537 (0x10001)
```

### 4.3 Generate Public Key

```bash
# Extract public key from private key
openssl rsa -in keys/private.pem -outform PEM -pubout -out keys/public.pem

# Verify public key was created
ls -la keys/public.pem

# Public key can have more permissive permissions
chmod 644 keys/public.pem
```

### 4.4 Verify Key Pair

```bash
# Verify private key format
openssl rsa -in keys/private.pem -check -noout

# Verify public key format
openssl rsa -in keys/public.pem -pubin -noout

# Both should output: "RSA key ok" or similar
```

**Security Note:**
- **NEVER** commit `keys/private.pem` to version control
- **NEVER** share the private key publicly
- Keep backups of keys in a secure location
- In production, consider using a key management service

---

## 5. Environment Configuration

### 5.1 Create Environment File

```bash
# Navigate to application root
cd /path/to/cre8.pw

# Copy the example environment file
cp .env.example .env

# Or for local development, use the local example
cp .env.local.example .env
```

### 5.2 Configure Environment Variables

Open `.env` in your text editor and configure the following sections:

#### 5.2.1 Application Configuration

```bash
APP_NAME=CRE8.pw
APP_ENV=development          # Use 'production' for production
APP_DEBUG=true              # Set to 'false' in production
APP_URL=http://localhost:8000  # Change port if needed
```

#### 5.2.2 Database Configuration

Update with your database credentials:

```bash
DB_HOST=localhost
DB_PORT=3306
DB_NAME=cre8pw              # Match the database you created
DB_USER=cre8_user          # Match the user you created
DB_PASS=your_secure_password_here  # Match the password you set
DB_CHARSET=utf8mb4
DB_COLLATION=utf8mb4_bin
DB_SSL_MODE=DISABLED        # Use 'REQUIRED' if using SSL
```

#### 5.2.3 JWT Configuration

Update paths to match your keys directory:

```bash
JWT_ALGO=RS256
JWT_PRIVATE_KEY_PATH=keys/private.pem      # Relative to project root
JWT_PUBLIC_KEY_PATH=keys/public.pem        # Relative to project root
JWT_ISSUER=http://localhost:8000            # Match APP_URL
JWT_AUDIENCE=http://localhost:8000/console  # Console audience
JWT_ACCESS_TTL=900                          # 15 minutes (seconds)
JWT_REFRESH_TTL=2592000                     # 30 days (seconds)
JWT_LEEWAY=10                               # Clock skew tolerance (seconds)
```

**Note:** For production, use absolute paths for JWT keys:
```bash
JWT_PRIVATE_KEY_PATH=/app/keys/private.pem
JWT_PUBLIC_KEY_PATH=/app/keys/public.pem
```

#### 5.2.4 CORS Configuration

For local development:

```bash
CORS_ALLOWED_ORIGINS=http://localhost:3000,http://localhost:8000
CORS_ALLOWED_METHODS=GET,POST,PUT,PATCH,DELETE,OPTIONS
CORS_ALLOWED_HEADERS=Authorization,Content-Type,X-Requested-With
CORS_EXPOSED_HEADERS=X-Total-Count,X-Page-Number
```

#### 5.2.5 CSRF Configuration

Generate a random 32-character secret:

```bash
# Generate random secret (Linux/macOS)
openssl rand -hex 16

# Or use PHP
php -r "echo bin2hex(random_bytes(16));"
```

Set in `.env`:

```bash
CSRF_SECRET=your_generated_32_character_secret_here
```

#### 5.2.6 Rate Limiting Configuration

For local development (more permissive):

```bash
RATE_LIMIT_GENERAL=1000 per minute
RATE_LIMIT_AUTH=100 per minute
RATE_LIMIT_API=600 per minute
RATE_LIMIT_BACKING=memory
```

#### 5.2.7 Logging Configuration

```bash
LOG_CHANNEL=stack
LOG_LEVEL=debug              # Use 'info' in production
LOG_PATH=logs               # Relative to project root
```

#### 5.2.8 Hashing Configuration

Default Argon2id settings (adjust based on server capacity):

```bash
APIKEY_HASH_ALGO=argon2id
PASSWORD_MEMORY_COST=65536   # 64 MB
PASSWORD_TIME_COST=4
PASSWORD_PARALLELISM=1
```

### 5.3 Create Logs Directory

```bash
# Create logs directory
mkdir -p logs

# Set permissions (web server needs write access)
chmod 755 logs
```

### 5.4 Verify Environment File

```bash
# Check that .env file exists
ls -la .env

# Verify it's not empty
wc -l .env

# Check that required variables are set (basic check)
grep -E "^(DB_|JWT_|APP_)" .env
```

---

## 6. Database Migrations

### 6.1 Run Migrations

The application includes a migration runner script. Run it to create all database tables:

```bash
# Navigate to application root
cd /path/to/cre8.pw

# Run migrations
php tools/db/migrate.php up
```

**Expected Output:**
```
Running migrations...
  → 001_create_owners.php
    ✓ Applied
  → 002_create_keys.php
    ✓ Applied
  → 003_create_key_public_ids.php
    ✓ Applied
  → 004_create_posts_and_comments.php
    ✓ Applied
  → 005_create_post_access.php
    ✓ Applied
  → 006_create_groups.php
    ✓ Applied
  → 007_create_keychains.php
    ✓ Applied
  → 008_create_refresh_tokens.php
    ✓ Applied
  → 009_create_audit_events.php
    ✓ Applied
  → 010_add_label_to_keys.php
    ✓ Applied
All migrations applied.
```

### 6.2 Verify Database Tables

Connect to the database and verify tables were created:

```bash
mysql -u cre8_user -p cre8pw
```

```sql
-- List all tables
SHOW TABLES;

-- You should see:
-- audit_events
-- comments
-- group_members
-- groups
-- key_public_ids
-- keychain_members
-- keychains
-- keys
-- migrations
-- owners
-- post_access
-- posts
-- refresh_tokens

-- Verify migrations table
SELECT * FROM migrations;

-- Exit
EXIT;
```

### 6.3 Troubleshooting Migrations

If migrations fail:

1. **Check database connection:**
   ```bash
   mysql -u cre8_user -p cre8pw -e "SELECT 1;"
   ```

2. **Check .env file:**
   ```bash
   grep DB_ .env
   ```

3. **Check error messages** - the migration script will show specific errors

4. **Rollback if needed:**
   ```bash
   php tools/db/migrate.php down
   ```

---

## 7. Starting the Application

### 7.1 Start PHP Built-in Server (Development)

For local development, use PHP's built-in server:

```bash
# Navigate to application root
cd /path/to/cre8.pw

# Start server on port 8000
php -S localhost:8000 -t public

# Or specify a different port
php -S localhost:8080 -t public
```

**Expected Output:**
```
PHP 8.3.x Development Server (http://localhost:8000) started
```

**Keep this terminal window open** - the server runs in the foreground.

### 7.2 Alternative: Use a Web Server

For production or more advanced setups, configure Apache or Nginx:

#### Apache Configuration Example

```apache
<VirtualHost *:80>
    ServerName cre8.local
    DocumentRoot /path/to/cre8.pw/public
    
    <Directory /path/to/cre8.pw/public>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>
    
    # Redirect to public directory
    RewriteEngine On
    RewriteRule ^(.*)$ /public/$1 [L]
</VirtualHost>
```

#### Nginx Configuration Example

```nginx
server {
    listen 80;
    server_name cre8.local;
    root /path/to/cre8.pw/public;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }
}
```

---

## 8. Verifying Installation

### 8.1 Health Check Endpoint

Test that the application is running:

```bash
# Using curl
curl http://localhost:8000/health

# Expected response:
# {"status":"ok","timestamp":"2026-01-23T12:00:00+00:00"}
```

### 8.2 JWKS Endpoint

Verify JWT keys are configured correctly:

```bash
# Using curl
curl http://localhost:8000/.well-known/jwks.json

# Expected response:
# {"keys":[{"kty":"RSA","use":"sig","alg":"RS256","kid":"...","n":"...","e":"AQAB"}]}
```

### 8.3 Check Application Logs

```bash
# View logs (if any errors occurred)
ls -la logs/

# Check for error logs
tail -f logs/*.log
```

---

## 9. Registering Your First Owner

### 9.1 Register via API (JSON)

Use `curl` or any HTTP client to register:

```bash
curl -X POST http://localhost:8000/console/owners \
  -H "Content-Type: application/json" \
  -d '{
    "email": "owner@example.com",
    "password": "SecurePassword123!"
  }'
```

**Expected Response:**
```json
{
  "data": {
    "owner_id": "3f2a9c1c4b7b4a2e8b6c1a9d2e3f4a5b"
  }
}
```

**Save the `owner_id`** - you'll need it for reference.

### 9.2 Register via HTML Form (if available)

If HTML templates are available:

1. Navigate to: `http://localhost:8000/console/register`
2. Fill in the registration form:
   - Email: `owner@example.com`
   - Password: `SecurePassword123!`
3. Submit the form
4. You should see a success message

### 9.3 Verify Owner Was Created

```bash
# Connect to database
mysql -u cre8_user -p cre8pw
```

```sql
-- Check owners table
SELECT id, email, created_at FROM owners;

-- You should see your new owner
-- Exit
EXIT;
```

---

## 10. Creating Your First Primary Author Key

After registering an Owner, you need to log in and create a Primary Author Key.

### 10.1 Login to Get Access Token

```bash
curl -X POST http://localhost:8000/console/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "owner@example.com",
    "password": "SecurePassword123!"
  }'
```

**Expected Response:**
```json
{
  "data": {
    "access_token": "eyJ0eXAiOiJKV1QiLCJhbGc...",
    "refresh_token": "rt_a1b2c3d4e5f6...",
    "expires_in": 900
  }
}
```

**CRITICAL:** Save both tokens:
- `access_token` - Use for authenticated requests (expires in 15 minutes)
- `refresh_token` - Use to get new access tokens (expires in 30 days)

### 10.2 Create Primary Author Key

Use the `access_token` from login to create a Primary Author Key:

```bash
# Replace YOUR_ACCESS_TOKEN with the token from step 10.1
curl -X POST http://localhost:8000/console/keys/primary \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_ACCESS_TOKEN" \
  -d '{
    "permissions": [
      "posts:create",
      "keys:issue",
      "posts:read",
      "comments:write"
    ],
    "label": "My First Primary Key"
  }'
```

**Expected Response:**
```json
{
  "data": {
    "key_id": "b5a1e8c0d9f04c3aa1b2c3d4e5f60718",
    "key_public_id": "apub_8cd1a2b3c4d5e6f7",
    "key_secret": "sec_a1b2c3d4e5f6g7h8i9j0k1l2m3n4o5p6",
    "type": "primary",
    "permissions": [
      "posts:create",
      "keys:issue",
      "posts:read",
      "comments:write"
    ],
    "active": true,
    "created_at": "2026-01-23T12:00:00Z"
  }
}
```

**CRITICAL:** Save these values securely:
- `key_public_id`: `apub_8cd1a2b3c4d5e6f7`
- `key_secret`: `sec_a1b2c3d4e5f6g7h8i9j0k1l2m3n4o5p6`

**Important Notes:**
- The `key_secret` is shown **only once** - save it immediately
- You'll use `key_public_id` + `key_secret` to authenticate as this key
- This key can create posts and mint child keys

### 10.3 Verify Key Was Created

```bash
# Connect to database
mysql -u cre8_user -p cre8pw
```

```sql
-- Check keys table
SELECT 
    HEX(id) as key_id_hex,
    type,
    active,
    created_at
FROM keys
WHERE type = 'primary'
LIMIT 5;

-- Check key_public_ids table
SELECT 
    HEX(key_id) as key_id_hex,
    key_public_id
FROM key_public_ids
LIMIT 5;

-- Exit
EXIT;
```

---

## 11. Creating Your First Post

Now that you have a Primary Author Key, you can create posts using the Gateway API.

### 11.1 Exchange ApiKey for JWT Token

First, exchange your `key_public_id` and `key_secret` for a JWT token:

```bash
# Replace with your actual key_public_id and key_secret from step 10.2
curl -X POST http://localhost:8000/api/auth/exchange \
  -H "Content-Type: application/json" \
  -H "Authorization: ApiKey apub_8cd1a2b3c4d5e6f7:sec_a1b2c3d4e5f6g7h8i9j0k1l2m3n4o5p6"
```

**Expected Response:**
```json
{
  "data": {
    "access_token": "eyJ0eXAiOiJKV1QiLCJhbGc...",
    "refresh_token": "rt_x1y2z3a4b5c6...",
    "expires_in": 900
  }
}
```

**Save the `access_token`** - this is a Key JWT (`typ=key`) for Gateway API access.

### 11.2 Create a Post

Use the Key JWT token to create a post:

```bash
# Replace YOUR_KEY_ACCESS_TOKEN with the token from step 11.1
curl -X POST http://localhost:8000/api/posts \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_KEY_ACCESS_TOKEN" \
  -d '{
    "content": "This is my first post on CRE8.pw!",
    "title": "My First Post"
  }'
```

**Expected Response:**
```json
{
  "data": {
    "post_id": "c7d8e9f0a1b2c3d4e5f6a7b8c9d0e1f2",
    "author_key_id": "b5a1e8c0d9f04c3aa1b2c3d4e5f60718",
    "content": "This is my first post on CRE8.pw!",
    "title": "My First Post",
    "created_at": "2026-01-23T12:00:00Z"
  }
}
```

**Save the `post_id`** - you'll need it for sharing.

### 11.3 Verify Post Was Created

```bash
# Connect to database
mysql -u cre8_user -p cre8pw
```

```sql
-- Check posts table
SELECT 
    HEX(id) as post_id_hex,
    HEX(author_key_id) as author_key_id_hex,
    title,
    LEFT(content, 50) as content_preview,
    created_at
FROM posts
ORDER BY created_at DESC
LIMIT 5;

-- Exit
EXIT;
```

---

## 12. Sharing Content with Others

To share a post with someone else, you need to:
1. Create a Use Key (limited-access key)
2. Grant the Use Key access to your post

### 12.1 Create a Use Key

Use your Primary Author Key to mint a Use Key:

```bash
# First, get a fresh Key JWT token (if expired, use refresh token)
# Replace YOUR_KEY_ACCESS_TOKEN with a valid Key JWT
# Replace YOUR_PRIMARY_KEY_ID with the key_id from step 10.2
curl -X POST http://localhost:8000/api/keys/YOUR_PRIMARY_KEY_ID/use \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_KEY_ACCESS_TOKEN" \
  -d '{
    "permissions": [
      "posts:read",
      "comments:write"
    ],
    "label": "Shared Access Key for Friend",
    "use_count": 10,
    "device_limit": 2
  }'
```

**Expected Response:**
```json
{
  "data": {
    "key_id": "d8e9f0a1b2c3d4e5f6a7b8c9d0e1f2a3",
    "key_public_id": "apub_9de2b3c4d5e6f7a8",
    "key_secret": "sec_b2c3d4e5f6g7h8i9j0k1l2m3n4o5p6q7",
    "type": "use",
    "permissions": [
      "posts:read",
      "comments:write"
    ],
    "use_count_limit": 10,
    "device_limit": 2,
    "active": true,
    "created_at": "2026-01-23T12:00:00Z"
  }
}
```

**Save these values:**
- `key_public_id`: `apub_9de2b3c4d5e6f7a8`
- `key_secret`: `sec_b2c3d4e5f6g7h8i9j0k1l2m3n4o5p6q7`

**Share these with the recipient** - they'll use them to access your post.

### 12.2 Grant Access to Post

Grant the Use Key access to your post:

```bash
# Replace YOUR_KEY_ACCESS_TOKEN with a valid Key JWT
# Replace YOUR_POST_ID with the post_id from step 11.2
# Replace USE_KEY_ID with the key_id from step 12.1
curl -X POST http://localhost:8000/api/posts/YOUR_POST_ID/access \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_KEY_ACCESS_TOKEN" \
  -d '{
    "target_type": "key",
    "target_id": "USE_KEY_ID",
    "permission_mask": 3
  }'
```

**Permission Mask Values:**
- `1` = VIEW only
- `2` = COMMENT only
- `3` = VIEW + COMMENT (0x01 | 0x02)
- `8` = MANAGE_ACCESS
- `11` = VIEW + COMMENT + MANAGE_ACCESS (0x01 | 0x02 | 0x08)

**Expected Response:**
```json
{
  "data": {
    "access_id": "e9f0a1b2c3d4e5f6a7b8c9d0e1f2a3b4",
    "post_id": "c7d8e9f0a1b2c3d4e5f6a7b8c9d0e1f2",
    "target_type": "key",
    "target_id": "d8e9f0a1b2c3d4e5f6a7b8c9d0e1f2a3",
    "permission_mask": 3,
    "created_at": "2026-01-23T12:00:00Z"
  }
}
```

### 12.3 Recipient Accesses the Post

The recipient can now access your post using the Use Key:

**Step 1: Exchange ApiKey for JWT**
```bash
curl -X POST http://localhost:8000/api/auth/exchange \
  -H "Content-Type: application/json" \
  -H "Authorization: ApiKey apub_9de2b3c4d5e6f7a8:sec_b2c3d4e5f6g7h8i9j0k1l2m3n4o5p6q7"
```

**Step 2: Read the Post**
```bash
# Use the access_token from Step 1
curl -X GET http://localhost:8000/api/posts/YOUR_POST_ID \
  -H "Authorization: Bearer ACCESS_TOKEN_FROM_STEP_1"
```

**Step 3: Comment on the Post**
```bash
curl -X POST http://localhost:8000/api/posts/YOUR_POST_ID/comments \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer ACCESS_TOKEN_FROM_STEP_1" \
  -d '{
    "body": "Great post! Thanks for sharing."
  }'
```

### 12.4 Verify Access Grant

```bash
# Connect to database
mysql -u cre8_user -p cre8pw
```

```sql
-- Check post_access table
SELECT 
    HEX(id) as access_id_hex,
    HEX(post_id) as post_id_hex,
    target_type,
    HEX(target_id) as target_id_hex,
    permission_mask,
    created_at
FROM post_access
ORDER BY created_at DESC
LIMIT 5;

-- Exit
EXIT;
```

---

## 13. Troubleshooting

### 13.1 Application Won't Start

**Problem:** PHP server fails to start

**Solutions:**
1. Check PHP version: `php -v` (must be 8.3+)
2. Check port availability: `netstat -an | grep 8000` (Linux) or `lsof -i :8000` (macOS)
3. Try a different port: `php -S localhost:8080 -t public`
4. Check file permissions: `ls -la public/index.php`

### 13.2 Database Connection Errors

**Problem:** "Connection refused" or "Access denied"

**Solutions:**
1. Verify database is running: `systemctl status mariadb` or `brew services list`
2. Check credentials in `.env`: `grep DB_ .env`
3. Test connection manually: `mysql -u cre8_user -p cre8pw`
4. Check user privileges: `SHOW GRANTS FOR 'cre8_user'@'localhost';`

### 13.3 JWT Key Errors

**Problem:** "JWT key file not found" or "Invalid PEM format"

**Solutions:**
1. Verify keys exist: `ls -la keys/`
2. Check paths in `.env`: `grep JWT_ .env`
3. Use absolute paths if relative paths fail
4. Regenerate keys if corrupted: `rm keys/*.pem` and repeat Section 4

### 13.4 Migration Errors

**Problem:** Migrations fail with SQL errors

**Solutions:**
1. Check database exists: `mysql -e "SHOW DATABASES LIKE 'cre8pw';"`
2. Check user has privileges: `SHOW GRANTS FOR 'cre8_user'@'localhost';`
3. Check charset/collation: `SHOW CREATE DATABASE cre8pw;`
4. Rollback and retry: `php tools/db/migrate.php down` then `up`

### 13.5 Authentication Errors

**Problem:** "Invalid credentials" or "Unauthorized"

**Solutions:**
1. Verify Owner exists: `SELECT * FROM owners WHERE email = 'your@email.com';`
2. Check password hash: Ensure Argon2id is supported (`php -m | grep sodium`)
3. Verify JWT token format: Token should start with `eyJ`
4. Check token expiration: Tokens expire after 15 minutes (access) or 30 days (refresh)

### 13.6 Permission Errors

**Problem:** "Forbidden" or "Missing permission"

**Solutions:**
1. Verify key permissions: Check `permissions_json` in `keys` table
2. Ensure key is active: `SELECT active FROM keys WHERE id = HEX_TO_BINARY('...');`
3. Check key type: Use Keys cannot create posts or mint keys
4. Verify permission strings match exactly: `posts:create`, `keys:issue`, etc.

### 13.7 CORS Errors (Browser)

**Problem:** "CORS policy" errors in browser console

**Solutions:**
1. Check `CORS_ALLOWED_ORIGINS` in `.env`
2. Add your origin: `CORS_ALLOWED_ORIGINS=http://localhost:3000,http://localhost:8000`
3. Restart the application after changing `.env`
4. For development, you can temporarily use `*` (not recommended for production)

### 13.8 Log Files

Check application logs for detailed error messages:

```bash
# View all logs
ls -la logs/

# View specific log channel
tail -f logs/api.log
tail -f logs/auth.log
tail -f logs/security.log
tail -f logs/db.log
```

### 13.9 Getting Help

If you encounter issues not covered here:

1. **Check Documentation:**
   - See [/TOC.md](../../TOC.md) for the master index
   - See [/SSOT.md](../../SSOT.md) for the SSOT hub
   - See [table-of-contents.md](../table-of-contents.md) for the full documentation catalog
   - See [logging-and-audit.md](../09-operations/logging-and-audit.md) for troubleshooting and observability

2. **Verify Configuration:**
   - Run bootstrap validator: Check `src/Utilities/BootstrapValidator.php`
   - Verify all required environment variables are set

3. **Check Application Status:**
   - Health endpoint: `curl http://localhost:8000/health`
   - JWKS endpoint: `curl http://localhost:8000/.well-known/jwks.json`

---

## Quick Reference

### Essential Commands

```bash
# Start application
php -S localhost:8000 -t public

# Run migrations
php tools/db/migrate.php up

# Rollback migrations
php tools/db/migrate.php down

# Check health
curl http://localhost:8000/health

# View logs
tail -f logs/*.log
```

### Key Endpoints

- **Health:** `GET /health`
- **JWKS:** `GET /.well-known/jwks.json`
- **Register Owner:** `POST /console/owners`
- **Login:** `POST /console/login`
- **Mint Primary Key:** `POST /console/keys/primary` (Owner JWT required)
- **ApiKey Exchange:** `POST /api/auth/exchange`
- **Create Post:** `POST /api/posts` (Key JWT required)
- **Grant Access:** `POST /api/posts/{postId}/access` (Key JWT required)

### Important Files

- `.env` - Environment configuration
- `keys/private.pem` - JWT private key (keep secure!)
- `keys/public.pem` - JWT public key
- `logs/` - Application logs
- `tools/db/migrate.php` - Migration runner

---

## Next Steps

After completing this installation guide, you should:

1. **Explore the API:**
   - Review [api-reference.md](../06-api-reference/api-reference.md) for all endpoints
   - Try creating Secondary Author Keys
   - Experiment with Groups and Keychains

2. **Understand the System:**
   - Read [introduction.md](../01-getting-started/introduction.md) for concepts
   - Review [authorization.md](../05-authentication-authorization/authorization.md) for permissions
   - Study [key-lifecycle.md](../03-core-concepts/key-lifecycle.md) for key management

3. **Production Deployment:**
   - See [implementation-guide.md](../08-implementation/implementation-guide.md) and [logging-and-audit.md](../09-operations/logging-and-audit.md) for operations guidance
   - Set `APP_ENV=production` in `.env`
   - Configure proper web server (Apache/Nginx)
   - Set up SSL/TLS certificates
   - Configure backup strategy

4. **Security Hardening:**
   - Use strong passwords
   - Restrict database user privileges
   - Use HTTPS in production
   - Regularly rotate JWT keys
   - Monitor audit logs

---

**Congratulations!** You've successfully installed and configured CRE8.pw. You can now create content, manage keys, and share posts with others using the hierarchical key-based authentication system.
