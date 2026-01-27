<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title ?? 'CRE8.pw') ?></title>
    <link rel="stylesheet" href="/css/styles.css">
</head>
<body>
    <div class="min-h-screen flex items-center justify-center" style="background: linear-gradient(135deg, var(--blue-50) 0%, var(--muted) 100%);">
        <div class="card" style="max-width: 600px; width: 100%; margin: 2rem; padding: 2rem;">
            <div class="space-y-4">
                <div class="text-center">
                    <h1>CRE8.pw</h1>
                    <p style="margin-top: 0.5rem; color: var(--muted-foreground);">Secure Hierarchical Content Sharing</p>
                </div>
                
                <div class="space-y-4" style="margin-top: 2rem;">
                    <div>
                        <h3>Welcome</h3>
                        <p style="color: var(--muted-foreground);">CRE8.pw is a secure platform for hierarchical content sharing with fine-grained access control.</p>
                    </div>
                    
                    <div class="flex gap-4" style="margin-top: 2rem;">
                        <a href="/console/register" class="btn btn-primary" style="flex: 1;">Register</a>
                        <a href="/console/login" class="btn btn-outline" style="flex: 1;">Login</a>
                    </div>
                    
                    <div style="margin-top: 2rem; padding-top: 2rem; border-top: 1px solid var(--border);">
                        <h3>Features</h3>
                        <ul class="space-y-2" style="list-style: none; padding: 0;">
                            <li>✓ Hierarchical key-based authentication</li>
                            <li>✓ Fine-grained permission system</li>
                            <li>✓ Group and keychain management</li>
                            <li>✓ Secure content sharing</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
