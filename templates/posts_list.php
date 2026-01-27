<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title ?? 'Posts - CRE8.pw') ?></title>
    <link rel="stylesheet" href="/css/styles.css">
</head>
<body>
    <div class="min-h-screen" style="background: var(--background);">
        <nav class="flex items-center justify-between" style="padding: 1rem 2rem; border-bottom: 1px solid var(--border);">
            <div>
                <a href="/" style="font-weight: 600; font-size: 1.25rem;">CRE8.pw</a>
            </div>
            <div class="flex gap-4">
                <a href="/console/dashboard" style="color: var(--muted-foreground);">Dashboard</a>
                <a href="/console/keys" style="color: var(--muted-foreground);">Keys</a>
                <a href="/console/groups" style="color: var(--muted-foreground);">Groups</a>
                <a href="/console/keychains" style="color: var(--muted-foreground);">Keychains</a>
                <a href="/console/posts" style="color: var(--muted-foreground);">Posts</a>
            </div>
        </nav>
        
        <main style="padding: 2rem; max-width: 1200px; margin: 0 auto;">
            <div class="space-y-6">
                <div>
                    <h1>Posts</h1>
                    <p style="color: var(--muted-foreground);">Manage your posts and access control</p>
                </div>
                
                <div id="posts-list" class="card" style="padding: 1.5rem;">
                    <p style="color: var(--muted-foreground);">Loading posts...</p>
                    <!-- Posts will be loaded via AJAX from /console/posts JSON endpoint -->
                </div>
            </div>
        </main>
    </div>
    
    <script>
        // TICKET T14.2: Full Owner Console page set
        // TICKET T14.3: Permissions-aware UI
        // TODO: Implement AJAX data fetching from /console/posts JSON endpoint
        // TODO: Implement post detail, access grant/revoke flows
        // TODO: Implement pagination for posts list
        // TODO: Hide/disable grant/revoke access buttons based on posts:access:manage permission
        
        // Placeholder for fetching posts
        // fetch('/console/posts', {
        //     headers: { 'Authorization': 'Bearer ' + getOwnerToken() }
        // })
        // .then(res => res.json())
        // .then(data => renderPostsList(data.data));
    </script>
</body>
</html>
