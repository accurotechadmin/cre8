<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title ?? 'Posts - CRE8.pw Gateway') ?></title>
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
                <a href="/gateway/groups" style="color: var(--muted-foreground);">Groups</a>
                <a href="/gateway/keychains" style="color: var(--muted-foreground);">Keychains</a>
            </div>
        </nav>
        
        <main style="padding: 2rem; max-width: 1200px; margin: 0 auto;">
            <div class="space-y-6">
                <div class="flex items-center justify-between">
                    <div>
                        <h1>Posts</h1>
                        <p style="color: var(--muted-foreground);">Create and view posts</p>
                    </div>
                    <button class="btn btn-primary" onclick="showCreatePostModal()">Create Post</button>
                </div>
                
                <div id="posts-list" class="card" style="padding: 1.5rem;">
                    <p style="color: var(--muted-foreground);">Loading posts...</p>
                    <!-- Posts will be loaded via AJAX from /api/posts JSON endpoint -->
                </div>
            </div>
        </main>
    </div>
    
    <script>
        // TICKET T15.1: Gateway UI page set
        // TODO: Implement AJAX data fetching from /api/posts JSON endpoint
        // TODO: Implement create post form/modal (requires posts:create permission)
        // TODO: Implement post detail navigation
        // TODO: Hide/disable create button based on permissions
        
        function showCreatePostModal() {
            alert('Create post functionality will be implemented in T15.1');
        }
        
        // Placeholder for fetching posts
        // fetch('/api/posts', {
        //     headers: { 'Authorization': 'Bearer ' + getKeyToken() }
        // })
        // .then(res => res.json())
        // .then(data => renderPostsList(data.data));
    </script>
</body>
</html>
