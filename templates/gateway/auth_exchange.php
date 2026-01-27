<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title ?? 'API Key Exchange - CRE8.pw Gateway') ?></title>
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
                <a href="/gateway/auth/exchange" style="color: var(--muted-foreground);">Exchange</a>
                <a href="/gateway/posts" style="color: var(--muted-foreground);">Posts</a>
                <a href="/gateway/groups" style="color: var(--muted-foreground);">Groups</a>
            </div>
        </nav>
        
        <main style="padding: 2rem; max-width: 800px; margin: 0 auto;">
            <div class="space-y-6">
                <div>
                    <h1>API Key Exchange</h1>
                    <p style="color: var(--muted-foreground);">Exchange your API key for a JWT access token</p>
                </div>
                
                <div class="card" style="padding: 1.5rem;">
                    <form method="POST" action="/api/auth/exchange" id="exchange-form" class="space-y-4">
                        <?php if ($csrf_name && $csrf_value): ?>
                        <input type="hidden" name="<?= htmlspecialchars($csrf_name) ?>" value="<?= htmlspecialchars($csrf_value) ?>">
                        <?php endif; ?>
                        
                        <div class="form-group">
                            <label for="api_key">API Key (apub_...:secret)</label>
                            <input type="text" id="api_key" name="api_key" required class="input" placeholder="apub_...:secret">
                            <p style="font-size: 0.875rem; color: var(--muted-foreground); margin-top: 0.5rem;">
                                Format: apub_publicid:secret
                            </p>
                        </div>
                        
                        <div id="error-message" style="display: none; color: var(--destructive); padding: 0.5rem; background: var(--muted); border-radius: var(--radius);"></div>
                        
                        <button type="submit" class="btn btn-primary" style="width: 100%;">Exchange</button>
                    </form>
                </div>
                
                <div class="card" style="padding: 1.5rem;">
                    <h3>Instructions</h3>
                    <ol style="margin-top: 1rem; padding-left: 1.5rem; color: var(--muted-foreground);">
                        <li>Enter your API key in the format: <code>apub_publicid:secret</code></li>
                        <li>Click Exchange to receive an access token and refresh token</li>
                        <li>Use the access token in the Authorization header: <code>Bearer &lt;token&gt;</code></li>
                        <li>Use the refresh token to get a new access token when it expires</li>
                    </ol>
                </div>
            </div>
        </main>
    </div>
    
    <script>
        // TICKET T15.1: Gateway UI page set
        // TODO: Implement form submission to /api/auth/exchange
        // TODO: Store tokens securely (localStorage or secure cookie)
        // TODO: Handle errors and display success message
    </script>
</body>
</html>
