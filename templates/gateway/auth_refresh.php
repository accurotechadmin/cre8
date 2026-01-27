<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title ?? 'Token Refresh - CRE8.pw Gateway') ?></title>
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
                <a href="/gateway/auth/refresh" style="color: var(--muted-foreground);">Refresh</a>
            </div>
        </nav>
        
        <main style="padding: 2rem; max-width: 800px; margin: 0 auto;">
            <div class="space-y-6">
                <div>
                    <h1>Token Refresh</h1>
                    <p style="color: var(--muted-foreground);">Refresh your access token using a refresh token</p>
                </div>
                
                <div class="card" style="padding: 1.5rem;">
                    <form method="POST" action="/api/auth/refresh" id="refresh-form" class="space-y-4">
                        <?php if ($csrf_name && $csrf_value): ?>
                        <input type="hidden" name="<?= htmlspecialchars($csrf_name) ?>" value="<?= htmlspecialchars($csrf_value) ?>">
                        <?php endif; ?>
                        
                        <div class="form-group">
                            <label for="refresh_token">Refresh Token</label>
                            <input type="text" id="refresh_token" name="refresh_token" required class="input" placeholder="Enter refresh token">
                        </div>
                        
                        <div id="error-message" style="display: none; color: var(--destructive); padding: 0.5rem; background: var(--muted); border-radius: var(--radius);"></div>
                        
                        <button type="submit" class="btn btn-primary" style="width: 100%;">Refresh</button>
                    </form>
                </div>
            </div>
        </main>
    </div>
    
    <script>
        // TICKET T15.1: Gateway UI page set
        // TODO: Implement form submission to /api/auth/refresh
        // TODO: Handle token rotation and store new tokens
    </script>
</body>
</html>
