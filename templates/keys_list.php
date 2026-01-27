<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title ?? 'Keys - CRE8.pw') ?></title>
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
                <div class="flex items-center justify-between">
                    <div>
                        <h1>Keys</h1>
                        <p style="color: var(--muted-foreground);">Manage your authentication keys</p>
                    </div>
                    <?php
                    // TICKET T14.3: Permissions-aware UI
                    $permissions = $permissions ?? [];
                    if (hasPermission($permissions, 'keys:issue')): ?>
                        <button class="btn btn-primary" onclick="showMintKeyModal()">Mint Primary Key</button>
                    <?php else: ?>
                        <button class="btn btn-primary" disabled title="Requires keys:issue permission">Mint Primary Key</button>
                    <?php endif; ?>
                </div>
                
                <div id="keys-list" class="card" style="padding: 1.5rem;">
                    <p style="color: var(--muted-foreground);">Loading keys...</p>
                    <!-- Keys will be loaded via AJAX from /console/keys JSON endpoint -->
                </div>
            </div>
        </main>
    </div>
    
    <script>
        // TICKET T14.2: Full Owner Console page set
        // TICKET T14.3: Permissions-aware UI
        // TODO: Implement AJAX data fetching from /console/keys JSON endpoint
        // TODO: Implement key detail, lineage, rotate, activate/deactivate actions
        // TODO: Implement mint primary key form/modal
        // TODO: Hide/disable rotate/activate/deactivate buttons based on keys:rotate and keys:state:update permissions
        
        function showMintKeyModal() {
            alert('Mint key functionality will be implemented in T14.2');
        }
        
        // Placeholder for fetching keys
        // fetch('/console/keys', {
        //     headers: { 'Authorization': 'Bearer ' + getOwnerToken() }
        // })
        // .then(res => res.json())
        // .then(data => renderKeysList(data.data));
    </script>
</body>
</html>
