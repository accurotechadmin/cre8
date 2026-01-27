<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Share Post - CRE8.pw Gateway</title>
    <link rel="stylesheet" href="/css/styles.css">
    <style>
        .wizard-step {
            display: none;
        }
        .wizard-step.active {
            display: block;
        }
        .step-indicator {
            display: flex;
            justify-content: space-between;
            margin-bottom: 2rem;
            padding: 1rem;
            background: var(--muted);
            border-radius: var(--radius);
        }
        .step-item {
            flex: 1;
            text-align: center;
            padding: 0.5rem;
            position: relative;
        }
        .step-item::after {
            content: '';
            position: absolute;
            top: 50%;
            right: 0;
            width: 100%;
            height: 2px;
            background: var(--border);
            z-index: 0;
        }
        .step-item:last-child::after {
            display: none;
        }
        .step-item.completed {
            color: var(--primary);
        }
        .step-item.active {
            font-weight: 600;
            color: var(--primary);
        }
        .step-number {
            display: inline-block;
            width: 2rem;
            height: 2rem;
            line-height: 2rem;
            border-radius: 50%;
            background: var(--background);
            border: 2px solid var(--border);
            margin-bottom: 0.5rem;
            position: relative;
            z-index: 1;
        }
        .step-item.completed .step-number {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }
        .step-item.active .step-number {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }
        .credentials-display {
            background: var(--muted);
            padding: 1rem;
            border-radius: var(--radius);
            margin-top: 1rem;
            font-family: monospace;
            word-break: break-all;
        }
        .credentials-display code {
            display: block;
            margin: 0.5rem 0;
        }
        .copy-btn {
            margin-top: 0.5rem;
        }
    </style>
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
                <a href="/gateway/share" style="color: var(--muted-foreground);">Share Post</a>
            </div>
        </nav>
        
        <main style="padding: 2rem; max-width: 1000px; margin: 0 auto;">
            <div class="space-y-6">
                <div>
                    <h1>Share Post Workflow</h1>
                    <p style="color: var(--muted-foreground);">Complete workflow: Create post → Mint use key → Grant access → Share credentials</p>
                </div>
                
                <!-- Step Indicator -->
                <div class="step-indicator">
                    <div class="step-item active" id="step-indicator-1">
                        <div class="step-number">1</div>
                        <div>Create Post</div>
                    </div>
                    <div class="step-item" id="step-indicator-2">
                        <div class="step-number">2</div>
                        <div>Mint Use Key</div>
                    </div>
                    <div class="step-item" id="step-indicator-3">
                        <div class="step-number">3</div>
                        <div>Grant Access</div>
                    </div>
                    <div class="step-item" id="step-indicator-4">
                        <div class="step-number">4</div>
                        <div>Share Credentials</div>
                    </div>
                </div>
                
                <!-- Step 1: Create Post -->
                <div class="wizard-step active" id="step-1">
                    <div class="card" style="padding: 1.5rem;">
                        <h2>Step 1: Create Post</h2>
                        <p style="color: var(--muted-foreground); margin-bottom: 1.5rem;">
                            Create a new post that you want to share. Posts are private by default.
                        </p>
                        
                        <form id="create-post-form" class="space-y-4">
                            <?php if ($csrf_name && $csrf_value): ?>
                            <input type="hidden" name="<?= htmlspecialchars($csrf_name) ?>" value="<?= htmlspecialchars($csrf_value) ?>">
                            <?php endif; ?>
                            
                            <div class="form-group">
                                <label for="post_title">Post Title</label>
                                <input type="text" id="post_title" name="title" required class="input" placeholder="Enter post title">
                            </div>
                            
                            <div class="form-group">
                                <label for="post_content">Post Content</label>
                                <textarea id="post_content" name="content" required class="input" rows="6" placeholder="Enter post content"></textarea>
                            </div>
                            
                            <div id="post-error" style="display: none; color: var(--destructive); padding: 0.5rem; background: var(--muted); border-radius: var(--radius);"></div>
                            
                            <button type="submit" class="btn btn-primary">Create Post</button>
                        </form>
                        
                        <div id="post-success" style="display: none; margin-top: 1rem;">
                            <div class="credentials-display">
                                <strong>Post Created Successfully!</strong>
                                <code>Post ID: <span id="created-post-id"></span></code>
                            </div>
                            <button onclick="nextStep(2)" class="btn btn-primary" style="margin-top: 1rem;">Continue to Step 2</button>
                        </div>
                    </div>
                </div>
                
                <!-- Step 2: Mint Use Key -->
                <div class="wizard-step" id="step-2">
                    <div class="card" style="padding: 1.5rem;">
                        <h2>Step 2: Mint Use Key</h2>
                        <p style="color: var(--muted-foreground); margin-bottom: 1.5rem;">
                            Create a use key for the recipient. Use keys cannot have <code>posts:create</code> or <code>keys:issue</code> permissions.
                        </p>
                        
                        <form id="mint-use-key-form" class="space-y-4">
                            <?php if ($csrf_name && $csrf_value): ?>
                            <input type="hidden" name="<?= htmlspecialchars($csrf_name) ?>" value="<?= htmlspecialchars($csrf_value) ?>">
                            <?php endif; ?>
                            
                            <div class="form-group">
                                <label for="author_key_id">Author Key ID</label>
                                <input type="text" id="author_key_id" name="authorKeyId" required class="input" placeholder="apub_... (your author key)">
                                <p style="font-size: 0.875rem; color: var(--muted-foreground); margin-top: 0.5rem;">
                                    The author key that will mint this use key
                                </p>
                            </div>
                            
                            <div class="form-group">
                                <label for="use_key_permissions">Permissions (comma-separated)</label>
                                <input type="text" id="use_key_permissions" name="permissions" required class="input" placeholder="posts:read,comments:write">
                                <p style="font-size: 0.875rem; color: var(--muted-foreground); margin-top: 0.5rem;">
                                    Cannot include posts:create or keys:issue
                                </p>
                            </div>
                            
                            <div class="form-group">
                                <label for="use_key_label">Label (optional)</label>
                                <input type="text" id="use_key_label" name="label" class="input" placeholder="Use key for Alice">
                            </div>
                            
                            <div class="form-group">
                                <label for="use_count">Use Count (optional)</label>
                                <input type="number" id="use_count" name="use_count" class="input" placeholder="Limit number of uses">
                            </div>
                            
                            <div class="form-group">
                                <label for="device_limit">Device Limit (optional)</label>
                                <input type="number" id="device_limit" name="device_limit" class="input" placeholder="Limit number of devices">
                            </div>
                            
                            <div id="use-key-error" style="display: none; color: var(--destructive); padding: 0.5rem; background: var(--muted); border-radius: var(--radius);"></div>
                            
                            <div class="flex gap-4">
                                <button type="button" onclick="previousStep(1)" class="btn btn-outline">Back</button>
                                <button type="submit" class="btn btn-primary">Mint Use Key</button>
                            </div>
                        </form>
                        
                        <div id="use-key-success" style="display: none; margin-top: 1rem;">
                            <div class="credentials-display">
                                <strong>Use Key Created Successfully!</strong>
                                <code>Key ID: <span id="created-use-key-id"></span></code>
                                <code>Public ID: <span id="created-use-key-public-id"></span></code>
                                <code>Secret: <span id="created-use-key-secret"></span></code>
                                <button onclick="copyCredentials('use-key')" class="btn btn-outline copy-btn">Copy Credentials</button>
                            </div>
                            <button onclick="nextStep(3)" class="btn btn-primary" style="margin-top: 1rem;">Continue to Step 3</button>
                        </div>
                    </div>
                </div>
                
                <!-- Step 3: Grant Access -->
                <div class="wizard-step" id="step-3">
                    <div class="card" style="padding: 1.5rem;">
                        <h2>Step 3: Grant Access</h2>
                        <p style="color: var(--muted-foreground); margin-bottom: 1.5rem;">
                            Grant the use key access to your post with the desired permission mask.
                        </p>
                        
                        <form id="grant-access-form" class="space-y-4">
                            <?php if ($csrf_name && $csrf_value): ?>
                            <input type="hidden" name="<?= htmlspecialchars($csrf_name) ?>" value="<?= htmlspecialchars($csrf_value) ?>">
                            <?php endif; ?>
                            
                            <div class="form-group">
                                <label for="grant_post_id">Post ID</label>
                                <input type="text" id="grant_post_id" name="postId" required class="input" placeholder="Post ID from Step 1">
                                <p style="font-size: 0.875rem; color: var(--muted-foreground); margin-top: 0.5rem;">
                                    The post ID created in Step 1
                                </p>
                            </div>
                            
                            <div class="form-group">
                                <label for="grant_target_id">Use Key ID</label>
                                <input type="text" id="grant_target_id" name="target_id" required class="input" placeholder="Use Key ID from Step 2">
                                <p style="font-size: 0.875rem; color: var(--muted-foreground); margin-top: 0.5rem;">
                                    The use key ID created in Step 2
                                </p>
                            </div>
                            
                            <div class="form-group">
                                <label for="permission_mask">Permission Mask</label>
                                <select id="permission_mask" name="permission_mask" required class="input">
                                    <option value="1">VIEW (0x01) - Read only</option>
                                    <option value="2">COMMENT (0x02) - Can comment</option>
                                    <option value="3" selected>VIEW + COMMENT (0x03) - Read and comment</option>
                                    <option value="9">VIEW + MANAGE_ACCESS (0x09) - Read and manage access</option>
                                    <option value="11">VIEW + COMMENT + MANAGE_ACCESS (0x0B) - Full access</option>
                                </select>
                                <p style="font-size: 0.875rem; color: var(--muted-foreground); margin-top: 0.5rem;">
                                    VIEW=1, COMMENT=2, MANAGE_ACCESS=8 (can combine)
                                </p>
                            </div>
                            
                            <div id="grant-error" style="display: none; color: var(--destructive); padding: 0.5rem; background: var(--muted); border-radius: var(--radius);"></div>
                            
                            <div class="flex gap-4">
                                <button type="button" onclick="previousStep(2)" class="btn btn-outline">Back</button>
                                <button type="submit" class="btn btn-primary">Grant Access</button>
                            </div>
                        </form>
                        
                        <div id="grant-success" style="display: none; margin-top: 1rem;">
                            <div class="credentials-display">
                                <strong>Access Granted Successfully!</strong>
                                <code>Access ID: <span id="created-access-id"></span></code>
                            </div>
                            <button onclick="nextStep(4)" class="btn btn-primary" style="margin-top: 1rem;">Continue to Step 4</button>
                        </div>
                    </div>
                </div>
                
                <!-- Step 4: Share Credentials -->
                <div class="wizard-step" id="step-4">
                    <div class="card" style="padding: 1.5rem;">
                        <h2>Step 4: Share Credentials</h2>
                        <p style="color: var(--muted-foreground); margin-bottom: 1.5rem;">
                            Share these credentials with the recipient. They can use the ApiKey to exchange for a JWT token and access the post.
                        </p>
                        
                        <div class="credentials-display">
                            <strong>Share these credentials with the recipient:</strong>
                            <code>ApiKey: <span id="share-api-key"></span></code>
                            <code>Post ID: <span id="share-post-id"></span></code>
                            <code>Feed URL: <span id="share-feed-url"></span></code>
                            <button onclick="copyCredentials('share')" class="btn btn-outline copy-btn">Copy All Credentials</button>
                        </div>
                        
                        <div style="margin-top: 2rem;">
                            <h3>Recipient Instructions</h3>
                            <ol style="margin-top: 1rem; padding-left: 1.5rem; color: var(--muted-foreground);">
                                <li>Exchange the ApiKey for a JWT token: <code>POST /api/auth/exchange</code></li>
                                <li>Use the JWT token to view the post: <code>GET /api/posts/{postId}</code></li>
                                <li>Or view the feed: <code>GET /api/feed/use/{useKeyId}</code></li>
                                <li>Comment on the post: <code>POST /api/posts/{postId}/comments</code></li>
                            </ol>
                        </div>
                        
                        <div class="flex gap-4" style="margin-top: 2rem;">
                            <button type="button" onclick="previousStep(3)" class="btn btn-outline">Back</button>
                            <button onclick="window.location.href='/gateway/posts'" class="btn btn-primary">Done</button>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
    
    <script>
        // TICKET T15.2: Sharing workflow UI
        // Store workflow state
        let workflowState = {
            postId: null,
            useKeyId: null,
            useKeyPublicId: null,
            useKeySecret: null,
            accessId: null
        };
        
        function showStep(stepNum) {
            // Hide all steps
            document.querySelectorAll('.wizard-step').forEach(step => {
                step.classList.remove('active');
            });
            
            // Show current step
            document.getElementById(`step-${stepNum}`).classList.add('active');
            
            // Update step indicators
            document.querySelectorAll('.step-item').forEach((item, index) => {
                item.classList.remove('active', 'completed');
                if (index + 1 === stepNum) {
                    item.classList.add('active');
                } else if (index + 1 < stepNum) {
                    item.classList.add('completed');
                }
            });
        }
        
        function nextStep(stepNum) {
            showStep(stepNum);
        }
        
        function previousStep(stepNum) {
            showStep(stepNum);
        }
        
        function copyCredentials(type) {
            let text = '';
            if (type === 'use-key') {
                text = `ApiKey: ${workflowState.useKeyPublicId}:${workflowState.useKeySecret}`;
            } else if (type === 'share') {
                text = `ApiKey: ${workflowState.useKeyPublicId}:${workflowState.useKeySecret}\nPost ID: ${workflowState.postId}\nFeed URL: /api/feed/use/${workflowState.useKeyId}`;
            }
            
            navigator.clipboard.writeText(text).then(() => {
                alert('Credentials copied to clipboard!');
            }).catch(err => {
                console.error('Failed to copy:', err);
            });
        }
        
        // Step 1: Create Post
        document.getElementById('create-post-form').addEventListener('submit', async (e) => {
            e.preventDefault();
            const formData = new FormData(e.target);
            const data = {
                title: formData.get('title'),
                content: formData.get('content')
            };
            
            // TODO: T15.2 - Implement AJAX call to POST /api/posts
            // const token = getKeyToken(); // Get from localStorage or session
            // const response = await fetch('/api/posts', {
            //     method: 'POST',
            //     headers: {
            //         'Content-Type': 'application/json',
            //         'Authorization': `Bearer ${token}`
            //     },
            //     body: JSON.stringify(data)
            // });
            // const result = await response.json();
            
            // Simulate success for now
            workflowState.postId = 'abc123...'; // result.data.post_id
            document.getElementById('created-post-id').textContent = workflowState.postId;
            document.getElementById('create-post-form').style.display = 'none';
            document.getElementById('post-success').style.display = 'block';
        });
        
        // Step 2: Mint Use Key
        document.getElementById('mint-use-key-form').addEventListener('submit', async (e) => {
            e.preventDefault();
            const formData = new FormData(e.target);
            const authorKeyId = formData.get('authorKeyId');
            const data = {
                permissions: formData.get('permissions').split(',').map(p => p.trim()),
                label: formData.get('label') || null,
                use_count: formData.get('use_count') ? parseInt(formData.get('use_count')) : null,
                device_limit: formData.get('device_limit') ? parseInt(formData.get('device_limit')) : null
            };
            
            // TODO: T15.2 - Implement AJAX call to POST /api/keys/{authorKeyId}/use
            // const token = getKeyToken();
            // const response = await fetch(`/api/keys/${authorKeyId}/use`, {
            //     method: 'POST',
            //     headers: {
            //         'Content-Type': 'application/json',
            //         'Authorization': `Bearer ${token}`
            //     },
            //     body: JSON.stringify(data)
            // });
            // const result = await response.json();
            
            // Simulate success for now
            workflowState.useKeyId = 'use_xyz...'; // result.data.key_id
            workflowState.useKeyPublicId = 'apub_alice...'; // result.data.key_public_id
            workflowState.useKeySecret = 'sec_alice...'; // result.data.key_secret
            document.getElementById('created-use-key-id').textContent = workflowState.useKeyId;
            document.getElementById('created-use-key-public-id').textContent = workflowState.useKeyPublicId;
            document.getElementById('created-use-key-secret').textContent = workflowState.useKeySecret;
            document.getElementById('mint-use-key-form').style.display = 'none';
            document.getElementById('use-key-success').style.display = 'block';
        });
        
        // Step 3: Grant Access
        document.getElementById('grant-access-form').addEventListener('submit', async (e) => {
            e.preventDefault();
            const formData = new FormData(e.target);
            const postId = formData.get('postId');
            const data = {
                target_type: 'key',
                target_id: formData.get('target_id'),
                permission_mask: parseInt(formData.get('permission_mask'))
            };
            
            // TODO: T15.2 - Implement AJAX call to POST /api/posts/{postId}/access
            // const token = getKeyToken();
            // const response = await fetch(`/api/posts/${postId}/access`, {
            //     method: 'POST',
            //     headers: {
            //         'Content-Type': 'application/json',
            //         'Authorization': `Bearer ${token}`
            //     },
            //     body: JSON.stringify(data)
            // });
            // const result = await response.json();
            
            // Simulate success for now
            workflowState.accessId = 'access_123...'; // result.data.access_id
            document.getElementById('created-access-id').textContent = workflowState.accessId;
            document.getElementById('grant-access-form').style.display = 'none';
            document.getElementById('grant-success').style.display = 'block';
        });
        
        // Step 4: Populate share credentials
        function populateShareCredentials() {
            if (workflowState.postId && workflowState.useKeyPublicId && workflowState.useKeySecret && workflowState.useKeyId) {
                document.getElementById('share-api-key').textContent = `${workflowState.useKeyPublicId}:${workflowState.useKeySecret}`;
                document.getElementById('share-post-id').textContent = workflowState.postId;
                document.getElementById('share-feed-url').textContent = `/api/feed/use/${workflowState.useKeyId}`;
            }
        }
        
        // Auto-populate fields when moving between steps
        document.getElementById('grant_post_id').addEventListener('focus', () => {
            if (workflowState.postId) {
                document.getElementById('grant_post_id').value = workflowState.postId;
            }
        });
        
        document.getElementById('grant_target_id').addEventListener('focus', () => {
            if (workflowState.useKeyId) {
                document.getElementById('grant_target_id').value = workflowState.useKeyId;
            }
        });
        
        // Populate share credentials when step 4 is shown
        const step4Observer = new MutationObserver(() => {
            if (document.getElementById('step-4').classList.contains('active')) {
                populateShareCredentials();
            }
        });
        step4Observer.observe(document.getElementById('step-4'), { attributes: true, attributeFilter: ['class'] });
    </script>
</body>
</html>
