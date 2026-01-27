# Permission Matrix

## Core Permissions

### Owner Permissions (Console-scoped)
| Permission | Meaning | Used By | Notes |
|---|---|---|---|
| `owners:manage` | Manage owner profile/settings | Owner | Console self-management |
| `keys:issue` | Mint Primary Author Keys | Owner | Root capability; Console only |
| `keys:read` | List/view keys in owner scope | Owner | Console key inventory |
| `keys:rotate` | Rotate keys (retire + replace) | Owner | Console key lifecycle |
| `keys:state:update` | Activate/deactivate keys | Owner | Console key state management |
| `groups:manage` | Full CRUD on groups + membership | Owner | Console group administration |
| `groups:read` | Read-only group access | Owner/Key | Console listing |
| `keychains:manage` | Manage keychains + membership | Owner/Key | Both surfaces |
| `posts:admin:read` | Admin view of owner-scoped posts | Owner | Console post inventory |
| `posts:access:manage` | Grant/revoke group access to posts | Owner/Key | Both surfaces |

### Key Permissions (Gateway-scoped)
| Permission | Meaning | Used By | Notes |
|---|---|---|---|
| `keys:issue` | Mint Secondary Author or Use Keys | Primary Author, Secondary Author | Gateway key issuance |
| `posts:create` | Create new posts | Primary Author, Secondary Author | **NEVER granted to Use Keys** |
| `posts:read` | Read/list visible posts | All key types | Requires VIEW mask |
| `comments:write` | Write comments on posts | Use Keys (if granted), Author Keys | Requires COMMENT mask |
| `groups:read` | Read groups | All key types | Gateway group listing |
| `keychains:manage` | Manage external keychains | All key types (if granted) | Gateway keychain ops |

### Permission Envelope Rules
- Child keys MUST have permissions ⊆ parent permissions
- Use Keys MUST NEVER receive `posts:create` or `keys:issue`
- Permission sets are **immutable** once minted
- To change permissions: rotate key (new key with new permission set)

## Post Access Bitmasks

| Bit | Hex | Name | Meaning |
|---:|---:|---|---|
| 0 | 0x01 | VIEW | View/read the post |
| 1 | 0x02 | COMMENT | Create comments |
| 3 | 0x08 | MANAGE_ACCESS | Manage post access grants |

### Presets
- `READ_ONLY` = 0x01 (VIEW only)
- `INTERACT` = 0x03 (VIEW + COMMENT)
- `ADMIN` = 0x0B (VIEW + COMMENT + MANAGE_ACCESS)

### Combined Authorization
Actions require **BOTH**:
1. Global permission string
2. Post-scoped mask bit

Examples:
- Read post: `posts:read` + `VIEW`
- Comment: `comments:write` + `COMMENT`
- Grant access: `posts:access:manage` + `MANAGE_ACCESS`

## Role Definitions

### Owner Role
- Assigned to: Owners (human principals)
- Implied permissions:
  - `owners:manage`
  - `keys:issue` (primary mint)
  - `keys:read`
  - `keys:rotate`
  - `keys:state:update`
  - `groups:manage`
  - `keychains:manage`
  - `posts:admin:read`
  - `posts:access:manage`

### Author Role (Primary/Secondary Keys)
- Assigned to: Primary Author Keys, Secondary Author Keys
- Implied permissions (typical):
  - `keys:issue` (child key minting)
  - `posts:create`
  - `posts:read`
  - `comments:write`
  - `groups:read`
  - `keychains:manage`

### Use Role
- Assigned to: Use Keys
- Implied permissions (typical):
  - `posts:read`
  - `comments:write`
  - `groups:read`
- **NEVER** includes:
  - `posts:create`
  - `keys:issue`

## Permission Assignment Flow
1. Owner registers → gets `owner` role
2. Owner mints Primary Author Key → permissions specified at mint time
3. Primary/Secondary Author mints child → child permissions ⊆ parent permissions
4. Use Key minted → permissions specified, validated against parent envelope
5. Access tokens include explicit `permissions` array in JWT
