<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title ?? 'Post Detail - CRE8.pw Gateway') ?></title>
    <link rel="stylesheet" href="/css/styles.css">
</head>
<body>
    <div class="min-h-screen" style="background: var(--background);">
        <nav class="flex items-center justify-between" style="padding: 1rem 2rem; border-bottom: 1px solid var(--border);">
            <div>
                <a href="/" style="font-weight: 600; font-size: 1.25rem;">CRE8.pw</a>
                <span style="color: var(--muted-foreground); margin-left: 1rem;">Gateway API Client</span>
            </div>
            <div class="flex gap-4">
                <a href="/gateway/posts" style="color: var(--muted-foreground);">Posts</a>
            </div>
        </nav>
        
        <main style="padding: 2rem; max-width: 1200px; margin: 0 auto;">
            <div class="space-y-6">
                <div>
                    <h1>Post Detail</h1>
                    <?php if (isset($postId) && $postId): ?>
                    <p style="color: var(--muted-foreground); font-size: 0.875rem; margin-top: 0.5rem;">
                        Post ID: <code><?= htmlspecialchars($postId) ?></code>
                    </p>
                    <?php endif; ?>
                </div>
                
                <div id="post-detail" class="card" style="padding: 1.5rem;">
                    <p style="color: var(--muted-foreground);">Loading post...</p>
                    <!-- Post will be loaded via AJAX from /api/posts/{postId} JSON endpoint -->
                </div>
                
                <div class="flex gap-4">
                    <a href="/gateway/posts/<?= htmlspecialchars($postId ?? '') ?>/comments" class="btn btn-outline">View Comments</a>
                    <a href="/gateway/posts/<?= htmlspecialchars($postId ?? '') ?>/access" class="btn btn-outline">Manage Access</a>
                </div>
            </div>
        </main>
    </div>
    
    <script>
        // TICKET T15.1: Gateway UI page set
        // TODO: Implement AJAX data fetching from /api/posts/{postId} JSON endpoint
        // TODO: Requires posts:read permission + VIEW mask
        
        // Placeholder for fetching post
        // const postId = '<?= htmlspecialchars($postId ?? '') ?>';
        // fetch(`/api/posts/${postId}`, {
        //     headers: { 'Authorization': 'Bearer ' + getKeyToken() }
        // })
        // .then(res => res.json())
        // .then(data => renderPostDetail(data.data));
    </script>
</body>
</html>
