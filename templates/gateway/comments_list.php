<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title ?? 'Comments - CRE8.pw Gateway') ?></title>
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
                    <h1>Comments</h1>
                    <p style="color: var(--muted-foreground);">View and create comments on a post</p>
                    <?php if (isset($postId) && $postId): ?>
                    <p style="color: var(--muted-foreground); font-size: 0.875rem; margin-top: 0.5rem;">
                        Post ID: <code><?= htmlspecialchars($postId) ?></code>
                    </p>
                    <?php endif; ?>
                </div>
                
                <div class="card" style="padding: 1.5rem;">
                    <h3>Create Comment</h3>
                    <form method="POST" action="/api/posts/<?= htmlspecialchars($postId ?? '') ?>/comments" id="comment-form" class="space-y-4" style="margin-top: 1rem;">
                        <?php if ($csrf_name && $csrf_value): ?>
                        <input type="hidden" name="<?= htmlspecialchars($csrf_name) ?>" value="<?= htmlspecialchars($csrf_value) ?>">
                        <?php endif; ?>
                        
                        <div class="form-group">
                            <label for="body">Comment</label>
                            <textarea id="body" name="body" required class="input" rows="4" placeholder="Enter your comment"></textarea>
                        </div>
                        
                        <button type="submit" class="btn btn-primary">Post Comment</button>
                    </form>
                </div>
                
                <div id="comments-list" class="card" style="padding: 1.5rem;">
                    <h3>Comments</h3>
                    <p style="color: var(--muted-foreground); margin-top: 1rem;">Loading comments...</p>
                    <!-- Comments will be loaded via AJAX from /api/posts/{postId}/comments JSON endpoint -->
                </div>
            </div>
        </main>
    </div>
    
    <script>
        // TICKET T15.1: Gateway UI page set
        // TODO: Implement AJAX data fetching from /api/posts/{postId}/comments JSON endpoint
        // TODO: Implement comment creation form submission
        // TODO: Requires comments:write permission + COMMENT mask
        // TODO: Requires posts:read permission + VIEW mask for listing
        
        // Placeholder for fetching comments
        // const postId = '<?= htmlspecialchars($postId ?? '') ?>';
        // fetch(`/api/posts/${postId}/comments`, {
        //     headers: { 'Authorization': 'Bearer ' + getKeyToken() }
        // })
        // .then(res => res.json())
        // .then(data => renderComments(data.data));
    </script>
</body>
</html>
