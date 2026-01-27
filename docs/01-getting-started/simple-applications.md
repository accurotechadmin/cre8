# Simple Applications Built on CRE8.pw

CRE8.pw ships with **posts** (title + content) and **comments** as a default content model, plus **Owners → Author Keys → Use Keys** and per‑resource access control. You are **not** limited to that. Posts can be extended with a **topic**, **category**, or other fields, and you can introduce **entirely new content types**—articles, events, assets, etc.—by extending the codebase. The platform is designed for **pattern‑based extension**: find the post/comment implementation, copy it, and adapt. New content types and enriched posts reuse the same Controller → Service → Repository flow, the same key/access model, and the same sharing patterns.

---

## Extending Beyond “Simple” Post and Comment

### Enriching posts

The default **post** has `title` and `content`. You can extend it without inventing a new content type:

- Add a **topic** or **category** (e.g. migration adds `topic`; update `PostRepository`, validation, and API).
- Add **metadata** (e.g. `type`: `article` | `handout` | `event`) or store lightweight JSON in `content` for frontend use.
- Add **status**, **publish_at**, or other fields. Same pattern: migration → repository → service → controller → validation.

You’re still using “posts” as the entity; you’re just giving them more structure.

### Creating new content types

You can also add **wholly new content forms** (e.g. **events**, **articles**, **assets**):

- **New table** (e.g. `events`): own `id`, `author_key_id`, `initial_author_key_id`, plus event‑specific fields (name, start_at, location, etc.).
- **New repository, service, controller**: Follow the existing **Post** / **Comment** pattern. Copy `PostRepository` → `EventRepository`, `PostService` → `EventService`, `PostController` → `EventController` (or equivalent), then adapt.
- **New routes** (e.g. `POST /api/events`, `GET /api/events/{id}`) and **validation** entries. Hook into the same Gateway pipeline (Key JWT, permissions, etc.).
- **Access control**: Reuse the same ideas—grant Use Keys (or groups) access to specific events via an `event_access`‑style table and bitmasks, analogous to `post_access`. Or reuse `post_access` if you prefer to keep “event” as a specialized post type.

The codebase is **copy‑paste friendly**: add a new content type by mirroring the post/comment implementation and changing the domain logic. No new architectural concepts.

### Summary

| Approach | What you do | When it fits |
|----------|-------------|--------------|
| **Use as‑is** | Default post + comment, frontend only | Fastest path; “post” = generic content |
| **Enrich posts** | Add `topic`, `category`, `type`, etc. | Same entity, more structure |
| **New content type** | New table + repo/service/controller, same patterns | Distinct entity (events, articles, assets) |

---

## Example Applications

The ideas below can be built **as‑is** (post + comment), with **enriched posts** (e.g. topic), or with **new content types** (e.g. dedicated Event model). Pick the level of extension that fits.

---

### 1. Gated Content & Sharing

**Password‑protected link sharing**  
Content = post (or enriched post with `topic`). Author mints a Use Key (optional `use_count_limit=1`), grants VIEW, shares credentials. Recipient exchanges for JWT and fetches the resource. No account.

**One‑time secrets / pastebin‑style**  
Post = secret or paste. Use Key with `use_count_limit=1`, VIEW only. “View once” link.

**Invite‑only drops**  
Creator publishes posts (or a new “Drop” content type). Mints Use Keys, grants access. Recipients use **Use Key feed**; no registration.

**Read‑only handouts**  
Posts = handouts (optionally with `topic` = "handout"). Use Keys, VIEW only, shared with class/team.

---

### 2. Feedback & Comments

**Feedback / comment collection**  
Post = “Feedback for X” or survey. Use Key with COMMENT; recipients add **comments** as responses. Enrich with `topic` for “survey” vs “feedback” if desired.

**Shared annotation**  
Post = document or asset. Use Key with COMMENT; collaborators annotate via comments. Could be a dedicated “Document” type with its own access table.

**Bug / feature‑request board**  
Posts = issues/requests (optionally `topic` = "bug" | "feature"). Use Key with COMMENT for testers. Comments = feedback. Or introduce an **Issue** content type and replicate the pattern.

---

### 3. Newsletters & Articles

**Gated newsletter / paywalled articles**  
Posts = articles; optionally add `topic` or `category`. Use Keys for subscribers, VIEW (+ COMMENT). Use Key feed as newsletter view. `use_count_limit` for “N articles” access.

**Member‑only updates**  
Same idea; one Use Key per member/tier, grant access to the right posts (or **MemberUpdate** content type if you extend).

**Micro‑course / tutorials**  
Posts = lessons (or new **Lesson** type). Use Key per student/cohort, VIEW + COMMENT for Q&A. Feed = curriculum.

---

### 4. Portals & Dashboards

**White‑label client portal**  
Agency creates posts (or **ClientAnnouncement** / **Deliverable** types) per client. One Use Key per client, VIEW or VIEW+COMMENT. Client uses Use Key feed as portal.

**Simple KB / support site**  
Posts = KB articles (optional `topic` for categories). Use Keys for customers, VIEW, optionally COMMENT for “contact us”.

**Tip‑jar / thank‑you access**  
Posts = thank‑you content. Use Keys for supporters, VIEW only.

---

### 5. Events & RSVP

**Gated event info**  
Either **post** with `topic` = "event" and metadata in `content`, or a **new Event content type** (name, start_at, location, etc.). Use Keys for invitees, VIEW; optional COMMENT for RSVP.

**Invite‑only RSVP**  
Same; Use Key = invite. View event + comment to RSVP.

---

### 6. API & Integration

**API‑gated assets**  
**Posts** as asset metadata (URL + fields) or a dedicated **Asset** content type. Use Keys = API keys. Clients use Key JWT to list/fetch. Simple gating.

**Webhook‑style consumers**  
Your app creates posts (or event summaries). Grant a Use Key to an external system; it polls Use Key feed or fetches by ID. Lightweight distribution.

---

### 7. Lightweight Membership

**Tiered access**  
Posts (or new content types) = member content. Different Use Keys per tier; grant access per tier. Members use keys only.

**Single‑key membership**  
One Use Key per member; grant access to all relevant content. Feed + comments.

---

## What Makes These “Simple”

- **Use as‑is or extend**: Start with default post + comment; add **topic** / **category** or **new content types** as needed.
- **Extension is straightforward**: Migrations, new repository/service/controller following post/comment patterns. Same key model, same access ideas.
- **Use Keys = access**: Share keys instead of building signup/login.
- **Optional limits**: `use_count_limit`, `device_limit` for one‑time or restricted use.
- **Frontend‑heavy**: Much of the work is UI calling the API; backend changes are incremental.

---

## Implementation Notes

- **Frontend‑only (as‑is)**: Use the CRE8.pw SDK (see [SDK Specification](../11-development/sdk-specification.md)) or raw HTTP calls (`POST /api/auth/exchange`, `GET /api/feed/use/{useKeyId}`, `GET /api/posts/{id}`, `POST /api/posts/{id}/comments`). No CRE8.pw code changes.
- **Using the SDK**: The CRE8.pw SDK is currently available for PHP 8.3+. Python and Go SDKs are planned for the near future. See [SDK Specification](../11-development/sdk-specification.md) for complete documentation.
- **Enriching posts**: Add columns via migration; update `PostRepository`, `PostService`, validation, and API. Use `title`/`content`/`topic` (or JSON in `content`) for your semantics.
- **New content type**: Copy **Post** → **Event** (or **Article**, **Asset**): new table, `EventRepository`, `EventService`, `EventController`, routes, validation. Add `event_access` (or reuse `post_access` if you model events as a post subtype). Same layering and key/access rules.
- **Key delivery**: Share `key_public_id` + `key_secret` via email, link generator, or in‑app flow.
- **Groups**: Use **groups** and group‑based access when many keys share the same permissions.

See [implementation-guide.md](../08-implementation/implementation-guide.md) for patterns, [SDK Specification](../11-development/sdk-specification.md) for SDK usage, and [post-sharing.md](../03-core-concepts/post-sharing.md) and [feed-system.md](../06-api-reference/feed-system.md) for sharing and feeds.
