<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title ?? 'Keychains - CRE8.pw Gateway') ?></title>
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
                        <h1>Keychains</h1>
                        <p style="color: var(--muted-foreground);">Manage external keychains</p>
                    </div>
                    <button class="btn btn-primary" onclick="showCreateKeychainModal()">Create Keychain</button>
                </div>
                
                <div id="keychains-list" class="card" style="padding: 1.5rem;">
                    <p style="color: var(--muted-foreground);">Loading keychains...</p>
                    <!-- Keychains will be loaded via AJAX from /api/keychains JSON endpoint -->
                </div>
            </div>
        </main>
    </div>
    
    <script>
        // TICKET T15.1: Gateway UI page set
        // TODO: Implement AJAX data fetching from /api/keychains JSON endpoint
        // TODO: Implement create keychain form/modal (requires keychains:manage permission)
        // TODO: Implement keychain members management
        
        function showCreateKeychainModal() {
            alert('Create keychain functionality will be implemented in T15.1');
        }
        
        // Placeholder for fetching keychains
        // fetch('/api/keychains', {
        //     headers: { 'Authorization': 'Bearer ' + getKeyToken() }
        // })
        // .then(res => res.json())
        // .then(data => renderKeychainsList(data.data));
    </script>
</body>
</html>
