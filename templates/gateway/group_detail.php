<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title ?? 'Group Detail - CRE8.pw Gateway') ?></title>
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
                <a href="/gateway/groups" style="color: var(--muted-foreground);">Groups</a>
            </div>
        </nav>
        
        <main style="padding: 2rem; max-width: 1200px; margin: 0 auto;">
            <div class="space-y-6">
                <div>
                    <h1>Group Detail</h1>
                    <?php if (isset($groupId) && $groupId): ?>
                    <p style="color: var(--muted-foreground); font-size: 0.875rem; margin-top: 0.5rem;">
                        Group ID: <code><?= htmlspecialchars($groupId) ?></code>
                    </p>
                    <?php endif; ?>
                </div>
                
                <div id="group-detail" class="card" style="padding: 1.5rem;">
                    <p style="color: var(--muted-foreground);">Loading group...</p>
                    <!-- Group will be loaded via AJAX from /api/groups/{groupId} JSON endpoint -->
                </div>
                
                <div>
                    <a href="/gateway/groups/<?= htmlspecialchars($groupId ?? '') ?>/members" class="btn btn-outline">View Members</a>
                </div>
            </div>
        </main>
    </div>
    
    <script>
        // TICKET T15.1: Gateway UI page set
        // TODO: Implement AJAX data fetching from /api/groups/{groupId} JSON endpoint
        // TODO: Requires groups:read permission
        
        // Placeholder for fetching group
        // const groupId = '<?= htmlspecialchars($groupId ?? '') ?>';
        // fetch(`/api/groups/${groupId}`, {
        //     headers: { 'Authorization': 'Bearer ' + getKeyToken() }
        // })
        // .then(res => res.json())
        // .then(data => renderGroupDetail(data.data));
    </script>
</body>
</html>
