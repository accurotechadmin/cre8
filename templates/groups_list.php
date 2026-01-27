<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title ?? 'Groups - CRE8.pw') ?></title>
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
                        <h1>Groups</h1>
                        <p style="color: var(--muted-foreground);">Manage access groups</p>
                    </div>
                    <?php
                    // TICKET T14.3: Permissions-aware UI
                    $permissions = $permissions ?? [];
                    if (hasPermission($permissions, 'groups:manage')): ?>
                        <button class="btn btn-primary" onclick="showCreateGroupModal()">Create Group</button>
                    <?php else: ?>
                        <button class="btn btn-primary" disabled title="Requires groups:manage permission">Create Group</button>
                    <?php endif; ?>
                </div>
                
                <div id="groups-list" class="card" style="padding: 1.5rem;">
                    <p style="color: var(--muted-foreground);">Loading groups...</p>
                    <!-- Groups will be loaded via AJAX from /console/groups JSON endpoint -->
                </div>
            </div>
        </main>
    </div>
    
    <script>
        // TICKET T14.2: Full Owner Console page set
        // TICKET T14.3: Permissions-aware UI
        // TODO: Implement AJAX data fetching from /console/groups JSON endpoint
        // TODO: Implement group detail, rename, members management
        // TODO: Implement create group form/modal
        // TODO: Hide/disable rename/delete/add/remove member buttons based on groups:manage permission
        
        function showCreateGroupModal() {
            alert('Create group functionality will be implemented in T14.2');
        }
        
        // Placeholder for fetching groups
        // fetch('/console/groups', {
        //     headers: { 'Authorization': 'Bearer ' + getOwnerToken() }
        // })
        // .then(res => res.json())
        // .then(data => renderGroupsList(data.data));
    </script>
</body>
</html>
