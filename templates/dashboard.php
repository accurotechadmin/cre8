<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title ?? 'Dashboard - CRE8.pw') ?></title>
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
                <a href="/console/logout" class="text-muted">Logout</a>
            </div>
        </nav>
        
        <main style="padding: 2rem; max-width: 1200px; margin: 0 auto;">
            <div class="space-y-6">
                <div>
                    <h1>Dashboard</h1>
                    <p style="color: var(--muted-foreground);">Manage your keys, groups, keychains, and posts</p>
                </div>
                
                <div class="grid grid-cols-2 gap-4" style="margin-top: 2rem;">
                    <?php
                    // TICKET T14.3: Permissions-aware UI
                    // Default permissions to empty array if not set
                    $permissions = $permissions ?? [];
                    ?>
                    
                    <?php if (hasPermission($permissions, 'keys:read')): ?>
                    <div class="card" style="padding: 1.5rem;">
                        <h3>Keys</h3>
                        <p style="color: var(--muted-foreground);">Manage your authentication keys</p>
                        <a href="/console/keys" class="btn btn-outline" style="margin-top: 1rem;">View Keys</a>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (hasPermission($permissions, 'groups:manage')): ?>
                    <div class="card" style="padding: 1.5rem;">
                        <h3>Groups</h3>
                        <p style="color: var(--muted-foreground);">Manage access groups</p>
                        <a href="/console/groups" class="btn btn-outline" style="margin-top: 1rem;">View Groups</a>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (hasPermission($permissions, 'keychains:manage')): ?>
                    <div class="card" style="padding: 1.5rem;">
                        <h3>Keychains</h3>
                        <p style="color: var(--muted-foreground);">Manage keychains</p>
                        <a href="/console/keychains" class="btn btn-outline" style="margin-top: 1rem;">View Keychains</a>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (hasPermission($permissions, 'posts:admin:read')): ?>
                    <div class="card" style="padding: 1.5rem;">
                        <h3>Posts</h3>
                        <p style="color: var(--muted-foreground);">Manage your posts</p>
                        <a href="/console/posts" class="btn btn-outline" style="margin-top: 1rem;">View Posts</a>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
