<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title ?? 'Keychains - CRE8.pw') ?></title>
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
                        <h1>Keychains</h1>
                        <p style="color: var(--muted-foreground);">Manage keychains</p>
                    </div>
                    <?php
                    // TICKET T14.3: Permissions-aware UI
                    $permissions = $permissions ?? [];
                    if (hasPermission($permissions, 'keychains:manage')): ?>
                        <button class="btn btn-primary" onclick="showCreateKeychainModal()">Create Keychain</button>
                    <?php else: ?>
                        <button class="btn btn-primary" disabled title="Requires keychains:manage permission">Create Keychain</button>
                    <?php endif; ?>
                </div>
                
                <div id="keychains-list" class="card" style="padding: 1.5rem;">
                    <p style="color: var(--muted-foreground);">Loading keychains...</p>
                    <!-- Keychains will be loaded via AJAX from /console/keychains JSON endpoint -->
                </div>
            </div>
        </main>
    </div>
    
    <script>
        // TICKET T14.2: Full Owner Console page set
        // TICKET T14.3: Permissions-aware UI
        // TODO: Implement AJAX data fetching from /console/keychains JSON endpoint
        // TODO: Implement keychain members management
        // TODO: Implement create keychain form/modal
        // TODO: Hide/disable add/remove member buttons based on keychains:manage permission
        
        function showCreateKeychainModal() {
            alert('Create keychain functionality will be implemented in T14.2');
        }
        
        // Placeholder for fetching keychains
        // fetch('/console/keychains', {
        //     headers: { 'Authorization': 'Bearer ' + getOwnerToken() }
        // })
        // .then(res => res.json())
        // .then(data => renderKeychainsList(data.data));
    </script>
</body>
</html>
