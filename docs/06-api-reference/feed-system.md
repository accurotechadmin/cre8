# Feed System

**Document Set:** CRE8.pw Documentation v1.0.0
**Last Updated:** 2026-01-21
**Status:** Canonical (SSoT)

**Scope:** Feed endpoints, visibility rules, pagination mechanics, and authorization enforcement for content access.

**SSoT Ownership:**
- Feed endpoint specifications (Use Key feed, Author feed)
- Visibility resolution rules
- Cursor-based pagination
- Feed authorization enforcement

---

## 1. Feed Concept

Feeds are ordered lists of posts visible to a principal, sorted newest-first by creation timestamp.

**Key Characteristics:**
- Time-ordered (newest first)
- Visibility-filtered (only posts the principal can access)
- Cursor-based pagination
- Efficient for high-volume scenarios

---

## 2. Use Key Feed

### 2.1 Endpoint

```
GET /api/feed/use/{useKeyId}
```

**Auth:** Key JWT where `key_id` matches path `{useKeyId}`

**Path Param Enforcement (Critical):**
- `{useKeyId}` must match `key_id` from JWT claim
- If mismatch: return `404 Not Found` (prevents cross-key snooping)

### 2.2 Query Parameters

| Param | Type | Default | Max | Description |
|---|---|---|---|---|
| `limit` | int | 20 | 100 | Posts per page |
| `before_id` | hex32 | null | - | Cursor for older posts (pagination backward) |
| `since_id` | hex32 | null | - | Cursor for newer posts (refresh) |

**Pagination Direction:**
- `before_id`: Fetch posts **older than** this post_id
- `since_id`: Fetch posts **newer than** this post_id

### 2.3 Visibility Rules

Posts appear in feed if:
1. Use Key has global `posts:read` permission, AND
2. `post_access` grants VIEW mask (0x01) to:
   - The Use Key directly (`target_type='key'`, `target_id=<key_id>`), OR
   - A group the Use Key belongs to (`target_type='group'`, `target_id` in user's groups)

**SQL Sketch:**
```sql
SELECT p.*
FROM posts p
INNER JOIN post_access pa ON p.id = pa.post_id
LEFT JOIN group_members gm ON pa.target_type = 'group' AND pa.target_id = gm.group_id
WHERE (
  -- Direct grant
  (pa.target_type = 'key' AND pa.target_id = ?)
  OR
  -- Group grant
  (pa.target_type = 'group' AND gm.key_id = ?)
)
AND (pa.permission_mask & 0x01) > 0  -- VIEW
ORDER BY p.created_at DESC
LIMIT ?;
```

### 2.4 Response Format

```json
{
  "data": [
    {
      "post_id": "c7d8e9f0a1b2c3d4e5f6a7b8c9d0e1f2",
      "author_key_id": "b5a1e8c0d9f04c3aa1b2c3d4e5f60718",
      "content": "Post content...",
      "title": "Optional title",
      "created_at": "2026-01-21T10:30:00Z"
    }
  ],
  "paging": {
    "limit": 20,
    "cursor": "c7d8e9f0a1b2c3d4e5f6a7b8c9d0e1f2"
  }
}
```

**Cursor:** Last post_id in the result set (for `before_id` in next request)

---

## 3. Author Key Feed (Future)

### 3.1 Endpoint

```
GET /api/feed/author
```

**Auth:** Author Key JWT (Primary or Secondary)

### 3.2 Visibility Rules

Posts appear if:
1. Authored by the Author Key or its descendants, OR
2. Visible via group memberships (groups the Author Key belongs to)

**Rationale:** Author feed shows "my content + content shared with me"

### 3.3 Query Parameters

Same as Use Key feed (`limit`, `before_id`, `since_id`)

---

## 4. Pagination Mechanics

### 4.1 Initial Request

```http
GET /api/feed/use/abc123?limit=20
```

**Response:**
```json
{
  "data": [ /* 20 posts */ ],
  "paging": {
    "limit": 20,
    "cursor": "last_post_id_in_this_page"
  }
}
```

### 4.2 Next Page (Older Posts)

```http
GET /api/feed/use/abc123?limit=20&before_id=last_post_id_in_this_page
```

Returns 20 posts older than `before_id`.

### 4.3 Refresh (Newer Posts)

```http
GET /api/feed/use/abc123?limit=20&since_id=first_post_id_i_have
```

Returns posts newer than `since_id` (up to 20).

### 4.4 Opaque vs Transparent Cursors

**Current Implementation:** Cursors are post IDs (hex32) — transparent

**Future Consideration:** Base64-encoded opaque cursors (hides implementation details)

---

## 5. Authorization Enforcement

### 5.1 Feed Endpoint Protection

**JwtKeyMiddleware:**
- Verifies JWT signature
- Attaches `key_id` to request context

**FeedController:**
```php
public function getUseFeed(Request $req, Response $res, array $args) {
    $pathKeyId = $args['useKeyId'];      // from route {useKeyId}
    $jwtKeyId = $req->getAttribute('key_id');  // from JWT
    
    if ($pathKeyId !== $jwtKeyId) {
        throw new NotFoundException(); // Hide existence of other feeds
    }
    
    // ...fetch feed
}
```

### 5.2 Post Visibility in Feed

Only include posts where Use Key has VIEW mask.

**Service Layer:**
```php
$posts = $this->postRepository->getVisiblePosts($keyId, $limit, $beforeId);
```

**Repository filters:**
- `post_access` grants with VIEW mask
- Group memberships
- Ordered by `created_at DESC`

---

## 6. Performance Considerations

### 6.1 Indexes

Critical indexes:
- `post_access (post_id, target_type, target_id)`
- `posts (created_at DESC)`
- `group_members (group_id, key_id)`

### 6.2 Pagination Limits

- Default: 20 posts
- Max: 100 posts
- Deep pagination (offset > 10000) discouraged (use cursors)

### 6.3 Caching Strategies

**Optional (Future):**
- Cache feed results per key_id for short TTL (60s)
- Invalidate on new posts or access grants
- Use Redis/Memcached for distributed cache

**Current:** No caching (database queries only)

---

## 7. Example Scenarios

### 7.1 Scenario: User Scrolls Through Feed

**Step 1:** Initial load
```
GET /api/feed/use/abc123?limit=20
→ Returns posts 1-20 with cursor=post_20_id
```

**Step 2:** Scroll down (load more)
```
GET /api/feed/use/abc123?limit=20&before_id=post_20_id
→ Returns posts 21-40 with cursor=post_40_id
```

**Step 3:** Pull to refresh (check for new posts)
```
GET /api/feed/use/abc123?limit=20&since_id=post_1_id
→ Returns any new posts created after post_1
```

### 7.2 Scenario: Use Key Loses Access

**Context:** Use Key had VIEW mask, post access is revoked

**Before Revocation:**
```
GET /api/feed/use/abc123?limit=20
→ Includes post_xyz
```

**After Revocation:**
```
GET /api/feed/use/abc123?limit=20
→ post_xyz no longer appears
```

**No error:** Feed updates reflect current access grants

---

## 8. Error Responses

**404 Not Found:**
- Path `{useKeyId}` doesn't match JWT `key_id`
- Use Key doesn't exist
- Use Key is inactive

**401 Unauthorized:**
- Invalid/expired JWT
- Missing JWT

**403 Forbidden:**
- Use Key lacks `posts:read` permission

**429 Rate Limited:**
- Too many feed requests
- Check `retry_after_seconds` in response

---

**Next:** Proceed to **[database-schema.md](../07-data-model/database-schema.md)** for complete database schema.
