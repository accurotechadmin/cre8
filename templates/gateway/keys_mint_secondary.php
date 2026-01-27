<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title ?? 'Mint Secondary Key - CRE8.pw Gateway') ?></title>
    <link rel="stylesheet" href="/css/styles.css">
</head>
<body>
    <div class="min-h-screen" style="background: var(--background);">
        <nav class="flex items-center justify-between" style="padding: 1rem 2rem; border-bottom: 1px solid var(--border);">
            <div>
                <a href="/" style="font-weight: 600; font-size: 1.25rem;">CRE8.pw</a>
                <span style="color: var(--muted-foreground); margin-left: 1rem;">Gateway API Client</span>
            </div>
        </nav>
        
        <main style="padding: 2rem; max-width: 800px; margin: 0 auto;">
            <div class="space-y-6">
                <div>
                    <h1>Mint Secondary Key</h1>
                    <p style="color: var(--muted-foreground);">Create a secondary author key with delegated permissions</p>
                    <?php if (isset($authorKeyId) && $authorKeyId): ?>
                    <p style="color: var(--muted-foreground); font-size: 0.875rem; margin-top: 0.5rem;">
                        Author Key ID: <code><?= htmlspecialchars($authorKeyId) ?></code>
                    </p>
                    <?php endif; ?>
                </div>
                
                <div class="card" style="padding: 1.5rem;">
                    <form method="POST" action="/api/keys/<?= htmlspecialchars($authorKeyId ?? '') ?>/secondary" id="mint-form" class="space-y-4">
                        <?php if ($csrf_name && $csrf_value): ?>
                        <input type="hidden" name="<?= htmlspecialchars($csrf_name) ?>" value="<?= htmlspecialchars($csrf_value) ?>">
                        <?php endif; ?>
                        
                        <div class="form-group">
                            <label for="permissions">Permissions (comma-separated)</label>
                            <input type="text" id="permissions" name="permissions" required class="input" placeholder="posts:create,posts:read,comments:write">
                            <p style="font-size: 0.875rem; color: var(--muted-foreground); margin-top: 0.5rem;">
                                Must be a subset of parent key permissions
                            </p>
                        </div>
                        
                        <div class="form-group">
                            <label for="label">Label (optional)</label>
                            <input type="text" id="label" name="label" class="input" placeholder="Secondary key for project X">
                        </div>
                        
                        <div id="error-message" style="display: none; color: var(--destructive); padding: 0.5rem; background: var(--muted); border-radius: var(--radius);"></div>
                        
                        <button type="submit" class="btn btn-primary" style="width: 100%;">Mint Secondary Key</button>
                    </form>
                </div>
            </div>
        </main>
    </div>
    
    <script>
        // TICKET T15.1: Gateway UI page set
        // TODO: Implement form submission to /api/keys/{authorKeyId}/secondary
        // TODO: Display new key credentials securely
        // TODO: Requires keys:issue permission
    </script>
</body>
</html>
