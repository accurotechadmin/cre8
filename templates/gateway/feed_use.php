<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title ?? 'Use Key Feed - CRE8.pw Gateway') ?></title>
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
                <a href="/gateway/feed/author" style="color: var(--muted-foreground);">Author Feed</a>
            </div>
        </nav>
        
        <main style="padding: 2rem; max-width: 1200px; margin: 0 auto;">
            <div class="space-y-6">
                <div>
                    <h1>Use Key Feed</h1>
                    <p style="color: var(--muted-foreground);">View posts visible to your Use Key</p>
                    <?php if (isset($useKeyId) && $useKeyId): ?>
                    <p style="color: var(--muted-foreground); font-size: 0.875rem; margin-top: 0.5rem;">
                        Use Key ID: <code><?= htmlspecialchars($useKeyId) ?></code>
                    </p>
                    <?php endif; ?>
                </div>
                
                <div id="feed-posts" class="card" style="padding: 1.5rem;">
                    <p style="color: var(--muted-foreground);">Loading feed...</p>
                    <!-- Feed will be loaded via AJAX from /api/feed/use/{useKeyId} JSON endpoint -->
                </div>
            </div>
        </main>
    </div>
    
    <script>
        // TICKET T15.1: Gateway UI page set
        // TODO: Implement AJAX data fetching from /api/feed/use/{useKeyId} JSON endpoint
        // TODO: Implement pagination (before_id/since_id)
        // TODO: JWT key_id must match useKeyId from path (enforced by backend)
        
        // Placeholder for fetching feed
        // const useKeyId = '<?= htmlspecialchars($useKeyId ?? '') ?>';
        // fetch(`/api/feed/use/${useKeyId}`, {
        //     headers: { 'Authorization': 'Bearer ' + getKeyToken() }
        // })
        // .then(res => res.json())
        // .then(data => renderFeed(data.data));
    </script>
</body>
</html>
