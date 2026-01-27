<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title ?? 'Grant Access - CRE8.pw Gateway') ?></title>
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
            </div>
        </nav>
        
        <main style="padding: 2rem; max-width: 800px; margin: 0 auto;">
            <div class="space-y-6">
                <div>
                    <h1>Grant Access</h1>
                    <p style="color: var(--muted-foreground);">Grant access to a post for a key or group</p>
                    <?php if (isset($postId) && $postId): ?>
                    <p style="color: var(--muted-foreground); font-size: 0.875rem; margin-top: 0.5rem;">
                        Post ID: <code><?= htmlspecialchars($postId) ?></code>
                    </p>
                    <?php endif; ?>
                </div>
                
                <div class="card" style="padding: 1.5rem;">
                    <form method="POST" action="/api/posts/<?= htmlspecialchars($postId ?? '') ?>/access" id="grant-form" class="space-y-4">
                        <?php if ($csrf_name && $csrf_value): ?>
                        <input type="hidden" name="<?= htmlspecialchars($csrf_name) ?>" value="<?= htmlspecialchars($csrf_value) ?>">
                        <?php endif; ?>
                        
                        <div class="form-group">
                            <label for="target_type">Target Type</label>
                            <select id="target_type" name="target_type" required class="input">
                                <option value="key">Key</option>
                                <option value="group">Group</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="target_id">Target ID (hex32)</label>
                            <input type="text" id="target_id" name="target_id" required class="input" placeholder="32-character hex ID">
                        </div>
                        
                        <div class="form-group">
                            <label for="permission_mask">Permission Mask</label>
                            <input type="number" id="permission_mask" name="permission_mask" required class="input" placeholder="0x01 (VIEW), 0x02 (COMMENT), 0x08 (MANAGE_ACCESS)">
                            <p style="font-size: 0.875rem; color: var(--muted-foreground); margin-top: 0.5rem;">
                                VIEW=1, COMMENT=2, MANAGE_ACCESS=8 (can combine: 3 = VIEW+COMMENT)
                            </p>
                        </div>
                        
                        <div id="error-message" style="display: none; color: var(--destructive); padding: 0.5rem; background: var(--muted); border-radius: var(--radius);"></div>
                        
                        <button type="submit" class="btn btn-primary" style="width: 100%;">Grant Access</button>
                    </form>
                </div>
            </div>
        </main>
    </div>
    
    <script>
        // TICKET T15.1: Gateway UI page set
        // TODO: Implement form submission to /api/posts/{postId}/access
        // TODO: Requires posts:access:manage permission + MANAGE_ACCESS mask
    </script>
</body>
</html>
