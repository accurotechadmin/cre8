<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title ?? 'Login - CRE8.pw') ?></title>
    <link rel="stylesheet" href="/css/styles.css">
</head>
<body>
    <div class="min-h-screen flex items-center justify-center" style="background: linear-gradient(135deg, var(--blue-50) 0%, var(--muted) 100%);">
        <div class="card" style="max-width: 400px; width: 100%; margin: 2rem; padding: 2rem;">
            <div class="space-y-4">
                <div class="text-center">
                    <h1>Login</h1>
                    <p style="color: var(--muted-foreground);">Sign in to your account</p>
                </div>
                
                <form method="POST" action="/console/login" id="login-form" class="space-y-4">
                    <?php if ($csrf_name && $csrf_value): ?>
                    <input type="hidden" name="<?= htmlspecialchars($csrf_name) ?>" value="<?= htmlspecialchars($csrf_value) ?>">
                    <?php endif; ?>
                    
                    <div class="form-group">
                        <label for="email">Email</label>
                        <input type="email" id="email" name="email" required placeholder="your@email.com">
                    </div>
                    
                    <div class="form-group">
                        <label for="password">Password</label>
                        <input type="password" id="password" name="password" required placeholder="••••••••">
                    </div>
                    
                    <div id="error-message" style="display: none; color: var(--destructive); padding: 0.5rem; background: var(--muted); border-radius: var(--radius);"></div>
                    
                    <button type="submit" class="btn btn-primary" style="width: 100%;">Login</button>
                    
                    <div class="text-center">
                        <a href="/console/register" class="text-muted">Don't have an account? Register</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</body>
</html>
