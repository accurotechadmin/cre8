// ============================================
// Toast Notification System
// ============================================
const Toast = {
    show(message, type = 'info') {
        const container = document.getElementById('toast-container');
        const toast = document.createElement('div');
        toast.className = `toast toast-${type}`;
        toast.textContent = message;
        container.appendChild(toast);
        
        setTimeout(() => {
            toast.style.animation = 'slideOut 0.3s ease-out';
            setTimeout(() => container.removeChild(toast), 300);
        }, 3000);
    },
    success(message) {
        this.show(message, 'success');
    },
    error(message) {
        this.show(message, 'error');
    }
};

// ============================================
// Router
// ============================================
class Router {
    constructor() {
        this.routes = {};
        this.currentPath = '/';
        
        window.addEventListener('popstate', () => {
            this.loadRoute(window.location.pathname);
        });
        
        document.addEventListener('click', (e) => {
            const link = e.target.closest('[data-link]');
            if (!link) {
                return;
            }
            if (e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) {
                return;
            }
            if (link.getAttribute('target') === '_blank') {
                return;
            }
            const href = link.getAttribute('href');
            if (!href) {
                return;
            }
            e.preventDefault();
            this.navigate(href);
        });
    }
    
    addRoute(path, handler) {
        this.routes[path] = handler;
    }
    
    navigate(path) {
        window.history.pushState({}, '', path);
        this.loadRoute(path);
    }
    
    loadRoute(path) {
        this.currentPath = path;
        
        // Try exact match first
        if (this.routes[path]) {
            this.routes[path]();
            return;
        }
        
        // Try pattern match
        for (const [route, handler] of Object.entries(this.routes)) {
            const pattern = route.replace(/:\w+/g, '([^/]+)');
            const regex = new RegExp(`^${pattern}$`);
            const match = path.match(regex);
            
            if (match) {
                const params = {};
                const paramNames = (route.match(/:\w+/g) || []).map(p => p.slice(1));
                paramNames.forEach((name, i) => {
                    params[name] = match[i + 1];
                });
                handler(params);
                return;
            }
        }
        
        // 404 fallback
        this.routes['/']();
    }
    
    start() {
        this.loadRoute(window.location.pathname);
    }
}

const router = new Router();

// ============================================
// Component Helpers
// ============================================
function createButton(text, options = {}) {
    const btn = document.createElement('button');
    btn.className = `btn ${options.variant || 'btn-primary'} ${options.size || ''}`;
    btn.innerHTML = `${options.icon || ''}${text}`;
    if (options.href) {
        btn.setAttribute('data-link', '');
        btn.setAttribute('href', options.href);
        btn.style.cursor = 'pointer';
        btn.onclick = (e) => {
            e.preventDefault();
            router.navigate(options.href);
        };
    }
    if (options.onclick) {
        btn.onclick = options.onclick;
    }
    return btn;
}

function createCard(title, content, options = {}) {
    return `
        <div class="card">
            ${title ? `
                <div class="card-header">
                    <h3 class="card-title">${title}</h3>
                    ${options.description ? `<p class="card-description">${options.description}</p>` : ''}
                </div>
            ` : ''}
            <div class="card-content">
                ${content}
            </div>
        </div>
    `;
}

function createPageTemplate(title, description, content, actions = '') {
    return `
        <div class="page-container">
            <div class="page-wrapper">
                <div class="page-header">
                    <div>
                        <h1 class="page-title">${title}</h1>
                        ${description ? `<p class="page-description">${description}</p>` : ''}
                    </div>
                    ${actions ? `<div class="page-actions">${actions}</div>` : ''}
                </div>
                ${createCard('', content)}
            </div>
        </div>
    `;
}

function createLayout(section, content) {
    const consoleNav = [
        {
            id: 'dashboard',
            title: 'Dashboard',
            icon: 'Home',
            href: '/console/dashboard',
        },
        {
            id: 'keys',
            title: 'Key Management',
            icon: 'Key',
            children: [
                { href: '/console/keys', label: 'All Keys' },
                { href: '/console/keys/primary', label: 'Mint Primary Key' },
            ],
        },
        {
            id: 'groups',
            title: 'Groups',
            icon: 'Users',
            children: [
                { href: '/console/groups', label: 'All Groups' },
            ],
        },
        {
            id: 'keychains',
            title: 'Keychains',
            icon: 'Package',
            children: [
                { href: '/console/keychains', label: 'All Keychains' },
            ],
        },
        {
            id: 'posts',
            title: 'Posts',
            icon: 'FileText',
            children: [
                { href: '/console/posts', label: 'All Posts' },
            ],
        },
    ];
    
    const apiNav = [
        {
            id: 'auth',
            title: 'Authentication',
            icon: 'Shield',
            children: [
                { href: '/api/auth/exchange', label: 'API Key Exchange' },
                { href: '/api/auth/refresh', label: 'Token Refresh' },
            ],
        },
        {
            id: 'apiKeys',
            title: 'Key Issuance',
            icon: 'Key',
            children: [
                { href: '/api/keys/example-key/secondary', label: 'Mint Secondary Key' },
                { href: '/api/keys/example-key/use', label: 'Mint Use Key' },
            ],
        },
        {
            id: 'apiPosts',
            title: 'Posts & Access',
            icon: 'FileText',
            children: [
                { href: '/api/posts', label: 'Create & List Posts' },
            ],
        },
        {
            id: 'feeds',
            title: 'Feeds',
            icon: 'FileText',
            children: [
                { href: '/api/feed/use/example-key', label: 'Use-Key Feed' },
                { href: '/api/feed/author', label: 'Author Feed' },
            ],
        },
        {
            id: 'apiGroups',
            title: 'Groups (Read-Only)',
            icon: 'Users',
            children: [
                { href: '/api/groups', label: 'All Groups' },
            ],
        },
        {
            id: 'apiKeychains',
            title: 'External Keychains',
            icon: 'Package',
            children: [
                { href: '/api/keychains', label: 'Create Keychain' },
            ],
        },
    ];
    
    const navItems = section === 'console' ? consoleNav : apiNav;
    const currentPath = router.currentPath;
    
    let navHTML = '';
    navItems.forEach(item => {
        if (item.children) {
            navHTML += `
                <div class="nav-section">
                    <button class="nav-section-header" onclick="toggleNav('${item.id}')">
                        <div class="flex items-center gap-2">
                            ${Icons[item.icon]}
                            <span>${item.title}</span>
                        </div>
                        <span class="chevron-rotate" id="chevron-${item.id}">${Icons.ChevronDown}</span>
                    </button>
                    <div class="nav-section-content" id="nav-${item.id}">
                        ${item.children.map(child => `
                            <a href="${child.href}" data-link class="nav-child-link ${currentPath === child.href ? 'active' : ''}">
                                ${child.label}
                            </a>
                        `).join('')}
                    </div>
                </div>
            `;
        } else {
            navHTML += `
                <a href="${item.href}" data-link class="nav-link ${currentPath === item.href ? 'active' : ''}">
                    ${Icons[item.icon]}
                    <span>${item.title}</span>
                </a>
            `;
        }
    });
    
    return `
        <div class="layout">
            <aside class="sidebar">
                <div class="sidebar-content">
                    <a href="/" data-link class="sidebar-header">
                        ${Icons.Shield}
                        <div>
                            <div class="sidebar-logo">CRE8.pw</div>
                            <div class="sidebar-subtitle">
                                ${section === 'console' ? 'Owner Console' : 'API Client'}
                            </div>
                        </div>
                    </a>
                    
                    <nav class="sidebar-nav">
                        ${navHTML}
                    </nav>
                    
                    <div class="sidebar-footer">
                        <a href="${section === 'console' ? '/api/posts' : '/console/dashboard'}" data-link class="nav-link">
                            ${section === 'console' ? Icons.Shield : Icons.Home}
                            <span>${section === 'console' ? 'Switch to API Client' : 'Switch to Console'}</span>
                        </a>
                        <a href="/" data-link class="nav-link">
                            ${Icons.LogOut}
                            <span>Logout</span>
                        </a>
                    </div>
                </div>
            </aside>
            
            <main class="main-content">
                ${content}
            </main>
        </div>
    `;
}

function toggleNav(id) {
    const content = document.getElementById(`nav-${id}`);
    const chevron = document.getElementById(`chevron-${id}`);
    content.classList.toggle('collapsed');
    chevron.classList.toggle('open');
}

// ============================================
// Page Components
// ============================================

// Landing Page
function renderLanding() {
    document.getElementById('app').innerHTML = `
        <div class="min-h-screen gradient-bg">
            <header class="landing-header">
                <div class="landing-header-content">
                    <div class="flex items-center gap-2">
                        <div style="width: 2rem; height: 2rem;" class="text-blue-600">${Icons.Shield}</div>
                        <span style="font-size: 1.5rem; font-weight: 700;">CRE8.pw</span>
                    </div>
                    <div class="flex gap-3">
                        <a href="/console/login" data-link class="btn btn-ghost">Login</a>
                        <a href="/console/register" data-link class="btn btn-primary">Register</a>
                    </div>
                </div>
            </header>
            
            <section class="hero-section">
                <div class="hero-content">
                    <h1 class="hero-title">
                        Two-Surface Authentication & Content Authorization
                    </h1>
                    <p class="hero-description">
                        Powerful key-based access control system for managing posts, groups, and permissions.
                        Use Keys for granular access to Posts made by Authors.
                    </p>
                    <div class="flex gap-4 justify-center">
                        <a href="/console/register" data-link class="btn btn-primary btn-lg">Get Started</a>
                        <a href="/console/login" data-link class="btn btn-outline btn-lg">View Demo</a>
                    </div>
                </div>
            </section>
            
            <section class="features-section">
                <h2 class="features-title">Key Features</h2>
                <div class="grid grid-cols-3 gap-8">
                    ${createCard('', `
                        <div style="width: 3rem; height: 3rem;" class="text-blue-600">${Icons.Key}</div>
                        <h3 style="margin-top: 1rem;">Hierarchical Keys</h3>
                        <p style="color: var(--gray-600); margin-top: 0.5rem;">
                            Create Primary Author Keys, Secondary Author Keys, and Use Keys with full lineage tracking and rotation support.
                        </p>
                    `)}
                    ${createCard('', `
                        <div style="width: 3rem; height: 3rem;" class="text-green-600">${Icons.Users}</div>
                        <h3 style="margin-top: 1rem;">Group Management</h3>
                        <p style="color: var(--gray-600); margin-top: 0.5rem;">
                            Organize keys into groups and manage permissions at scale with ease.
                        </p>
                    `)}
                    ${createCard('', `
                        <div style="width: 3rem; height: 3rem;" class="text-purple-600">${Icons.FileText}</div>
                        <h3 style="margin-top: 1rem;">Content Control</h3>
                        <p style="color: var(--gray-600); margin-top: 0.5rem;">
                            Fine-grained access control for posts with permission masks and access grants.
                        </p>
                    `)}
                    ${createCard('', `
                        <div style="width: 3rem; height: 3rem;" class="text-red-600">${Icons.Lock}</div>
                        <h3 style="margin-top: 1rem;">Secure by Default</h3>
                        <p style="color: var(--gray-600); margin-top: 0.5rem;">
                            Token-based authentication with automatic refresh and secure key exchange.
                        </p>
                    `)}
                    ${createCard('', `
                        <div style="width: 3rem; height: 3rem;" class="text-yellow-600">${Icons.Zap}</div>
                        <h3 style="margin-top: 1rem;">Easy Integration</h3>
                        <p style="color: var(--gray-600); margin-top: 0.5rem;">
                            RESTful API with comprehensive documentation and example implementations.
                        </p>
                    `)}
                    ${createCard('', `
                        <div style="width: 3rem; height: 3rem;" class="text-indigo-600">${Icons.Shield}</div>
                        <h3 style="margin-top: 1rem;">Owner Console</h3>
                        <p style="color: var(--gray-600); margin-top: 0.5rem;">
                            Complete control panel for managing all aspects of your authentication system.
                        </p>
                    `)}
                </div>
            </section>
            
            <section class="cta-section">
                <div class="cta-card">
                    <h2 style="font-size: 1.875rem; font-weight: 700; margin-bottom: 1rem;">Ready to get started?</h2>
                    <p style="font-size: 1.25rem; margin-bottom: 2rem; opacity: 0.9;">
                        Create your account and start managing keys and content today.
                    </p>
                    <a href="/console/register" data-link class="btn btn-secondary btn-lg">Register Now</a>
                </div>
            </section>
            
            <footer class="landing-footer">
                <div class="footer-content">
                    <div class="flex items-center gap-2">
                        ${Icons.Shield}
                        <span>© 2026 CRE8.pw</span>
                    </div>
                    <div class="footer-links">
                        <a href="#">Documentation</a>
                        <a href="#">API Reference</a>
                        <a href="#">Support</a>
                    </div>
                </div>
            </footer>
        </div>
    `;
}

// Owner Register
function renderOwnerRegister() {
    document.getElementById('app').innerHTML = `
        <div class="auth-container">
            ${createCard('Register', `
                <div class="auth-header">
                    <div class="flex justify-center">
                        <div style="width: 3rem; height: 3rem;" class="text-blue-600">${Icons.Shield}</div>
                    </div>
                    <h2 style="margin-top: 1rem;">Create your account</h2>
                    <p style="color: var(--gray-600); font-size: 0.875rem;">Start managing keys and content</p>
                </div>
                <div class="code-block" style="margin-bottom: 1rem;">
                    <pre>POST /console/owners</pre>
                </div>
                <form id="registerForm">
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" name="email" required>
                    </div>
                    <div class="form-group">
                        <label>Password</label>
                        <input type="password" name="password" required>
                    </div>
                    <button type="submit" class="btn btn-primary" style="width: 100%;">Register</button>
                </form>
                <div id="registerResult" style="margin-top: 1rem;"></div>
                <div class="card" style="margin-top: 1.5rem;">
                    <div class="card-content">
                        <h3>Next step</h3>
                        <p style="margin-top: 0.5rem;">After registering, continue to login to start your Owner session.</p>
                        <a href="/console/login" data-link class="btn btn-outline" style="margin-top: 1rem;">Go to Login</a>
                    </div>
                </div>
                <p style="text-align: center; margin-top: 1rem; font-size: 0.875rem;">
                    Already have an account? <a href="/console/login" data-link style="color: var(--blue-600);">Login</a>
                </p>
            `, { description: '' })}
        </div>
    `;
    
    document.getElementById('registerForm').onsubmit = (e) => {
        e.preventDefault();
        Toast.success('Registration successful!');
        document.getElementById('registerResult').innerHTML = `
            <div class="card" style="margin-top: 1rem;">
                <div class="card-content">
                    <strong>Owner account created.</strong>
                    <p style="margin-top: 0.5rem;">Continue to login to access your console.</p>
                    <a href="/console/login" data-link class="btn btn-primary" style="margin-top: 1rem;">Login now</a>
                </div>
            </div>
        `;
    };
}

// Owner Login
function renderOwnerLogin() {
    document.getElementById('app').innerHTML = `
        <div class="auth-container">
            ${createCard('Login', `
                <div class="auth-header">
                    <div class="flex justify-center">
                        <div style="width: 3rem; height: 3rem;" class="text-blue-600">${Icons.Shield}</div>
                    </div>
                    <h2 style="margin-top: 1rem;">Welcome back</h2>
                    <p style="color: var(--gray-600); font-size: 0.875rem;">Sign in to your account</p>
                </div>
                <div class="code-block" style="margin-bottom: 1rem;">
                    <pre>POST /console/login</pre>
                </div>
                <form id="loginForm">
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" name="email" required>
                    </div>
                    <div class="form-group">
                        <label>Password</label>
                        <input type="password" name="password" required>
                    </div>
                    <button type="submit" class="btn btn-primary" style="width: 100%;">Login</button>
                </form>
                <div class="card" style="margin-top: 1.5rem;">
                    <div class="card-content">
                        <h3>Next step</h3>
                        <p style="margin-top: 0.5rem;">After signing in, you’ll land on your dashboard with quick links to keys, groups, keychains, and posts.</p>
                    </div>
                </div>
                <p style="text-align: center; margin-top: 1rem; font-size: 0.875rem;">
                    Don't have an account? <a href="/console/register" data-link style="color: var(--blue-600);">Register</a>
                </p>
            `, { description: '' })}
        </div>
    `;
    
    document.getElementById('loginForm').onsubmit = (e) => {
        e.preventDefault();
        // Accept any username/password - no validation required
        const email = e.target.email.value;
        const password = e.target.password.value;
        
        // Show success message
        Toast.success('Login successful!');
        
        // Redirect to dashboard immediately
        router.navigate('/console/dashboard');
    };
}

// Dashboard
function renderDashboard() {
    const stats = [
        { label: 'Total Keys', value: '24', icon: 'Key', color: 'text-blue-600' },
        { label: 'Groups', value: '8', icon: 'Users', color: 'text-green-600' },
        { label: 'Keychains', value: '5', icon: 'Package', color: 'text-purple-600' },
        { label: 'Posts', value: '156', icon: 'FileText', color: 'text-orange-600' },
    ];
    
    const content = `
        <h1 style="font-size: 1.875rem; font-weight: 700; margin-bottom: 0.5rem;">Owner Dashboard</h1>
        <p style="color: var(--gray-600); margin-bottom: 2rem;">Central hub for managing your authentication system</p>
        
        <div class="stats-grid">
            ${stats.map(stat => `
                <div class="card">
                    <div class="card-content">
                        <div class="stat-card-content">
                            <div>
                                <p class="stat-label">${stat.label}</p>
                                <p class="stat-value">${stat.value}</p>
                            </div>
                            <div style="width: 2rem; height: 2rem;" class="${stat.color}">${Icons[stat.icon]}</div>
                        </div>
                    </div>
                </div>
            `).join('')}
        </div>

        ${createCard('Quickstart Checklist', `
            <ul class="space-y-2">
                <li class="flex items-center gap-2"><span class="badge badge-success">1</span> Register your Owner account</li>
                <li class="flex items-center gap-2"><span class="badge badge-secondary">2</span> Login to start a session</li>
                <li class="flex items-center gap-2"><span class="badge badge-secondary">3</span> Mint a Primary Author Key for content creation</li>
                <li class="flex items-center gap-2"><span class="badge badge-secondary">4</span> Create groups and keychains</li>
                <li class="flex items-center gap-2"><span class="badge badge-secondary">5</span> Publish posts and grant access</li>
            </ul>
        `, { description: 'Owner onboarding flow: Register → Login → Dashboard' })}
        
        <h2 style="font-size: 1.25rem; font-weight: 600; margin-bottom: 1rem;">Quick Actions</h2>
        <div class="grid grid-cols-2 gap-4" style="margin-bottom: 2rem;">
            ${[
                { label: 'Mint Primary Key', href: '/console/keys/primary', icon: 'Key' },
                { label: 'Create Group', href: '/console/groups', icon: 'Users' },
                { label: 'Create Post', href: '/console/posts', icon: 'FileText' },
                { label: 'View All Keys', href: '/console/keys', icon: 'TrendingUp' },
            ].map(action => createCard('', `
                <div class="flex items-center gap-2" style="margin-bottom: 1rem;">
                    ${Icons[action.icon]}
                    <h3>${action.label}</h3>
                </div>
                <a href="${action.href}" data-link class="btn btn-outline" style="width: 100%;">Go to ${action.label}</a>
            `)).join('')}
        </div>

        <h2 style="font-size: 1.25rem; font-weight: 600; margin-bottom: 1rem;">Console Sections</h2>
        <div class="grid grid-cols-2 gap-4" style="margin-bottom: 2rem;">
            ${[
                { label: 'Keys', description: 'Manage keys, rotation, and lineage', href: '/console/keys', icon: 'Key' },
                { label: 'Groups', description: 'Organize members and permissions', href: '/console/groups', icon: 'Users' },
                { label: 'Keychains', description: 'Create and manage keychain membership', href: '/console/keychains', icon: 'Package' },
                { label: 'Posts', description: 'Publish and control access to content', href: '/console/posts', icon: 'FileText' },
            ].map(section => createCard('', `
                <div class="flex items-center gap-2" style="margin-bottom: 0.75rem;">
                    ${Icons[section.icon]}
                    <h3>${section.label}</h3>
                </div>
                <p style="color: var(--gray-600); margin-bottom: 1rem;">${section.description}</p>
                <a href="${section.href}" data-link class="btn btn-outline" style="width: 100%;">Open ${section.label}</a>
            `)).join('')}
        </div>
        
        ${createCard('Recent Activity', `
            <div class="space-y-4">
                ${[
                    { action: 'New primary key minted', time: '2 hours ago' },
                    { action: 'Group "Team Alpha" created', time: '5 hours ago' },
                    { action: 'Post #142 published', time: '1 day ago' },
                    { action: 'Key rotated: apub_8cd1a2b3c4d5e6f7', time: '2 days ago' },
                ].map(item => `
                    <div class="activity-item">
                        <div class="flex items-center gap-3">
                            <div class="activity-dot"></div>
                            <span style="font-size: 0.875rem;">${item.action}</span>
                        </div>
                        <span style="font-size: 0.875rem; color: var(--gray-500);">${item.time}</span>
                    </div>
                `).join('')}
            </div>
        `, { description: 'Latest actions in your system' })}
    `;
    
    document.getElementById('app').innerHTML = createLayout('console', `
        <div class="page-container">
            <div class="page-wrapper">
                ${content}
            </div>
        </div>
    `);
}

// Keys List
function renderKeysList() {
    const keys = [
        { id: 'a1b2c3d4e5f607181920212223242526', type: 'Primary Author', status: 'active', created: '2026-01-15', publicId: 'apub_8cd1a2b3c4d5e6f7' },
        { id: 'b2c3d4e5f60718192021222324252627', type: 'Secondary Author', status: 'active', created: '2026-01-18', publicId: 'apub_9de2b3c4d5e6f7g8' },
        { id: 'c3d4e5f6071819202122232425262728', type: 'Use', status: 'active', created: '2026-01-20', publicId: 'apub_0ef3c4d5e6f7g8h9' },
        { id: 'd4e5f607181920212223242526272829', type: 'Primary Author', status: 'deactivated', created: '2026-01-10', publicId: 'apub_1fg4d5e6f7g8h9i0' },
    ];
    
    const actions = `<a href="/console/keys/primary" data-link class="btn btn-primary">${Icons.Plus} Mint Primary Key</a>`;
    
    const content = `
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Key ID</th>
                        <th>Type</th>
                        <th>Status</th>
                        <th>Created</th>
                        <th class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    ${keys.map(key => `
                        <tr>
                            <td class="font-mono" style="font-size: 0.875rem;">${key.publicId || key.id}</td>
                            <td>
                                <span class="badge ${key.type === 'Primary Author' ? 'badge-default' : key.type === 'Secondary Author' ? 'badge-secondary' : 'badge-success'}">
                                    ${key.type}
                                </span>
                            </td>
                            <td>
                                <span class="badge ${key.status === 'active' ? 'badge-success' : 'badge-secondary'}">
                                    ${key.status}
                                </span>
                            </td>
                            <td>${key.created}</td>
                            <td class="text-right">
                                <a href="/console/keys/${key.id}" data-link class="btn btn-outline btn-sm">${Icons.Eye} Details</a>
                                <a href="/console/keys/${key.id}/lineage" data-link class="btn btn-ghost btn-sm">${Icons.GitBranch} Lineage</a>
                            </td>
                        </tr>
                    `).join('')}
                </tbody>
            </table>
        </div>
    `;
    
    document.getElementById('app').innerHTML = createLayout('console', 
        createPageTemplate('Keys List', 'Manage all keys owned by your account', content, actions)
    );
}

// Mint Primary Key
function renderMintPrimaryKey() {
    const content = `
        <form id="mintKeyForm">
            <div class="form-group">
                <label>Permissions (comma-separated)</label>
                <input type="text" name="permissions" placeholder="keys:issue,posts:create,posts:read,comments:write" required>
                <p style="font-size: 0.75rem; color: var(--gray-600); margin-top: 0.25rem;">
                    Available: keys:issue, posts:create, posts:read, comments:write, groups:read, keychains:manage, posts:access:manage
                </p>
            </div>
            <div class="form-group">
                <label>Key Label</label>
                <input type="text" name="label" placeholder="e.g., Main Account Key" required>
            </div>
            <div class="form-group">
                <label>Description</label>
                <textarea name="description" placeholder="Optional description for this key"></textarea>
            </div>
            <button type="submit" class="btn btn-primary">Mint Primary Key</button>
        </form>
        <div id="mintPrimaryResponse" style="margin-top: 1rem;"></div>
    `;
    
    document.getElementById('app').innerHTML = createLayout('console', 
        createPageTemplate('Mint Primary Key', 'Create a new primary key for your account', content)
    );
    
    document.getElementById('mintKeyForm').onsubmit = (e) => {
        e.preventDefault();
        Toast.success('Primary key minted successfully!');
        document.getElementById('mintPrimaryResponse').innerHTML = `
            <div class="code-block">
                <pre>{
  "data": {
    "key_id": "a1b2c3d4e5f607181920212223242526",
    "key_public_id": "apub_8cd1a2b3c4d5e6f7",
    "key_secret": "sk_live_xxxxxxxxxxxxx",
    "permissions": ["${e.target.permissions.value.split(',').join('","')}"],
    "label": "${e.target.label.value}",
    "created_at": "2026-01-25T10:30:00Z"
  }
}</pre>
            </div>
        `;
    };
}

// Key Detail
function renderKeyDetail(params) {
    const content = `
        <div class="space-y-4">
            <div>
                <h3>Key Information</h3>
                <div style="margin-top: 1rem;">
                    <p><strong>Key ID:</strong> <span class="font-mono">${params.keyId}</span></p>
                    <p><strong>Type:</strong> <span class="badge badge-default">Primary Author</span></p>
                    <p><strong>Status:</strong> <span class="badge badge-success">Active</span></p>
                    <p><strong>Public ID:</strong> <span class="font-mono">apub_8cd1a2b3c4d5e6f7</span></p>
                    <p><strong>Permissions:</strong> keys:issue, posts:create, posts:read, comments:write, groups:read, keychains:manage, posts:access:manage</p>
                    <p><strong>Label:</strong> Main Account Key</p>
                    <p><strong>Created:</strong> 2026-01-15</p>
                    <p><strong>Last Used:</strong> 2 hours ago</p>
                </div>
            </div>
            <div>
                <h3>Rotation History</h3>
                <ul style="margin-top: 1rem;" class="space-y-2">
                    <li>2026-01-15 — Key created</li>
                    <li>2026-02-03 — Rotated, previous key archived</li>
                </ul>
            </div>
            <div style="margin-top: 2rem;">
                <h3>Actions</h3>
                <div class="flex gap-2" style="margin-top: 1rem;">
                    <a href="/console/keys/${params.keyId}/rotate" data-link class="btn btn-outline">${Icons.RotateCw} Rotate Key</a>
                    <a href="/console/keys/${params.keyId}/lineage" data-link class="btn btn-outline">${Icons.GitBranch} View Lineage</a>
                    <a href="/console/keys/${params.keyId}/activate" data-link class="btn btn-ghost">${Icons.Check} Activate</a>
                    <a href="/console/keys/${params.keyId}/deactivate" data-link class="btn btn-ghost">${Icons.X} Deactivate</a>
                </div>
            </div>
        </div>
    `;
    
    document.getElementById('app').innerHTML = createLayout('console', 
        createPageTemplate('Key Details', `Viewing key: ${params.keyId}`, content)
    );
}

// Key Lineage
function renderKeyLineage(params) {
    const content = `
        <div>
            <p style="margin-bottom: 1rem;">Key hierarchy and relationships for ${params.keyId}</p>
            <div class="code-block">
                <pre>
Primary Author Key: ${params.keyId} (apub_8cd1a2b3c4d5e6f7)
├── Secondary Author Key: b2c3d4e5f60718192021222324252627 (apub_9de2b3c4d5e6f7g8)
│   ├── Use Key: c3d4e5f6071819202122232425262728 (apub_0ef3c4d5e6f7g8h9)
│   └── Use Key: d4e5f607181920212223242526272829 (apub_1fg4d5e6f7g8h9i0)
└── Secondary Author Key: e5f60718192021222324252627282930 (apub_2gh5e6f7g8h9i0j1)
    └── Use Key: f6071819202122232425262728293031 (apub_3hi6f7g8h9i0j1k2)
                </pre>
            </div>
        </div>
    `;
    
    document.getElementById('app').innerHTML = createLayout('console', 
        createPageTemplate('Key Lineage', 'View key hierarchy and relationships', content)
    );
}

// Rotate Key
function renderRotateKey(params) {
    const content = `
        <form id="rotateKeyForm">
            <p style="margin-bottom: 1rem;">Rotating key will create a new key and deactivate the current one. All access will be transferred to the new key.</p>
            <div class="code-block" style="margin-bottom: 1rem;">
                <pre>POST /console/keys/${params.keyId}/rotate</pre>
            </div>
            <div class="form-group">
                <label>Reason for Rotation</label>
                <textarea name="reason" placeholder="Optional reason for key rotation"></textarea>
            </div>
            <div class="flex gap-2">
                <button type="submit" class="btn btn-primary">Confirm Rotation</button>
                <a href="/console/keys/${params.keyId}" data-link class="btn btn-ghost">Cancel</a>
            </div>
        </form>
        <div id="rotateKeyResponse" style="margin-top: 1rem;"></div>
    `;
    
    document.getElementById('app').innerHTML = createLayout('console', 
        createPageTemplate('Rotate Key', `Rotate key: ${params.keyId}`, content)
    );
    
    document.getElementById('rotateKeyForm').onsubmit = (e) => {
        e.preventDefault();
        Toast.success('Key rotated successfully!');
        document.getElementById('rotateKeyResponse').innerHTML = `
            <div class="code-block">
                <pre>{
  "data": {
    "key_id": "a1b2c3d4e5f607181920212223242526",
    "key_public_id": "apub_8cd1a2b3c4d5e6f7",
    "key_secret": "sk_live_xxxxxxxxxxxxx",
    "status": "active",
    "rotated_at": "2026-01-25T10:30:00Z"
  }
}</pre>
            </div>
        `;
    };
}

// Activate/Deactivate Key
function renderActivateKey(params) {
    const content = `
        <form id="activateKeyForm">
            <p style="margin-bottom: 1rem;">Activate this key to allow it to be used for authentication.</p>
            <div class="code-block" style="margin-bottom: 1rem;">
                <pre>POST /console/keys/${params.keyId}/activate</pre>
            </div>
            <div class="flex gap-2">
                <button type="submit" class="btn btn-primary">Activate Key</button>
                <a href="/console/keys/${params.keyId}" data-link class="btn btn-ghost">Cancel</a>
            </div>
        </form>
    `;
    
    document.getElementById('app').innerHTML = createLayout('console', 
        createPageTemplate('Activate Key', `Activate key: ${params.keyId}`, content)
    );
    
    document.getElementById('activateKeyForm').onsubmit = (e) => {
        e.preventDefault();
        Toast.success('Key activated successfully!');
        setTimeout(() => router.navigate('/console/keys'), 1000);
    };
}

function renderDeactivateKey(params) {
    const content = `
        <form id="deactivateKeyForm">
            <p style="margin-bottom: 1rem; color: var(--destructive);">Warning: Deactivating this key will prevent it from being used for authentication.</p>
            <div class="form-group">
                <label style="display: flex; align-items: center; gap: 0.5rem;">
                    <input type="checkbox" name="cascade">
                    Cascade deactivation to descendants
                </label>
            </div>
            <div class="code-block" style="margin-bottom: 1rem;">
                <pre>POST /console/keys/${params.keyId}/deactivate</pre>
            </div>
            <div class="form-group">
                <label>Reason for Deactivation</label>
                <textarea name="reason" placeholder="Optional reason"></textarea>
            </div>
            <div class="flex gap-2">
                <button type="submit" class="btn btn-primary">Deactivate Key</button>
                <a href="/console/keys/${params.keyId}" data-link class="btn btn-ghost">Cancel</a>
            </div>
        </form>
    `;
    
    document.getElementById('app').innerHTML = createLayout('console', 
        createPageTemplate('Deactivate Key', `Deactivate key: ${params.keyId}`, content)
    );
    
    document.getElementById('deactivateKeyForm').onsubmit = (e) => {
        e.preventDefault();
        Toast.success('Key deactivated successfully!');
        setTimeout(() => router.navigate('/console/keys'), 1000);
    };
}

// Groups List
function renderGroupsList() {
    const groups = [
        { id: 'a1b2c3d4e5f607181920212223242526', name: 'Team Alpha', members: 12, created: '2026-01-10' },
        { id: 'b2c3d4e5f60718192021222324252627', name: 'Beta Testers', members: 8, created: '2026-01-12' },
        { id: 'c3d4e5f6071819202122232425262728', name: 'Premium Users', members: 45, created: '2026-01-15' },
    ];
    
    const content = `
        <div class="card" style="margin-bottom: 1.5rem;">
            <div class="card-content">
                <h3>Create Group</h3>
                <form id="createGroupForm" style="margin-top: 1rem;">
                    <div class="form-group">
                        <label>Group Name</label>
                        <input type="text" name="groupName" placeholder="e.g., VIP Owners" required>
                    </div>
                    <button type="submit" class="btn btn-primary">${Icons.Plus} Create Group</button>
                </form>
            </div>
        </div>
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Group ID</th>
                        <th>Name</th>
                        <th>Members</th>
                        <th>Created</th>
                        <th class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    ${groups.map(group => `
                        <tr>
                            <td class="font-mono" style="font-size: 0.875rem;">${group.id}</td>
                            <td><strong>${group.name}</strong></td>
                            <td>${group.members}</td>
                            <td>${group.created}</td>
                            <td class="text-right">
                                <a href="/console/groups/${group.id}" data-link class="btn btn-outline btn-sm">${Icons.Eye} View</a>
                                <a href="/console/groups/${group.id}/members" data-link class="btn btn-ghost btn-sm">${Icons.Users} Members</a>
                            </td>
                        </tr>
                    `).join('')}
                </tbody>
            </table>
        </div>
    `;
    
    document.getElementById('app').innerHTML = createLayout('console', 
        createPageTemplate('Groups', 'Manage groups and organize keys', content)
    );

    document.getElementById('createGroupForm').onsubmit = (e) => {
        e.preventDefault();
        Toast.success('Group created successfully!');
        e.target.reset();
    };
}

// Group Detail
function renderGroupDetail(params) {
    const content = `
        <div class="space-y-4">
            <div>
                <h3>Group Information</h3>
                <div style="margin-top: 1rem;">
                    <p><strong>Group ID:</strong> <span class="font-mono">${params.groupId}</span></p>
                    <p><strong>Name:</strong> Team Alpha</p>
                    <p><strong>Members:</strong> 12</p>
                    <p><strong>Created:</strong> 2026-01-10</p>
                </div>
            </div>
            <div style="margin-top: 2rem;">
                <h3>Actions</h3>
                <div class="flex gap-2" style="margin-top: 1rem;">
                    <a href="/console/groups/${params.groupId}/rename" data-link class="btn btn-outline">${Icons.Edit} Rename</a>
                    <a href="/console/groups/${params.groupId}/members" data-link class="btn btn-outline">${Icons.Users} Manage Members</a>
                </div>
            </div>
        </div>
    `;
    
    document.getElementById('app').innerHTML = createLayout('console', 
        createPageTemplate('Group Details', `Viewing group: ${params.groupId}`, content)
    );
}

// Rename Group
function renderRenameGroup(params) {
    const content = `
        <form id="renameGroupForm">
            <div class="form-group">
                <label>New Group Name</label>
                <input type="text" name="name" value="Team Alpha" required>
            </div>
            <div class="flex gap-2">
                <button type="submit" class="btn btn-primary">Save Changes</button>
                <a href="/console/groups/${params.groupId}" data-link class="btn btn-ghost">Cancel</a>
            </div>
        </form>
    `;
    
    document.getElementById('app').innerHTML = createLayout('console', 
        createPageTemplate('Rename Group', `Rename group: ${params.groupId}`, content)
    );
    
    document.getElementById('renameGroupForm').onsubmit = (e) => {
        e.preventDefault();
        Toast.success('Group renamed successfully!');
        setTimeout(() => router.navigate(`/console/groups/${params.groupId}`), 1000);
    };
}

// Group Members
function renderGroupMembers(params) {
    const members = [
        { keyId: 'key_primary_001', addedDate: '2026-01-10' },
        { keyId: 'key_secondary_045', addedDate: '2026-01-12' },
        { keyId: 'key_use_892', addedDate: '2026-01-15' },
    ];
    
    const content = `
        <div class="card" style="margin-bottom: 1.5rem;">
            <div class="card-content">
                <h3>Add Member</h3>
                <form id="addGroupMemberForm" style="margin-top: 1rem;">
                    <div class="form-group">
                        <label>Key ID (hex32 or apub_ format)</label>
                        <input type="text" name="keyId" placeholder="a1b2c3d4e5f607181920212223242526 or apub_8cd1a2b3c4d5e6f7" required>
                    </div>
                    <button type="submit" class="btn btn-primary">${Icons.Plus} Add Member</button>
                </form>
            </div>
        </div>
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Key ID</th>
                        <th>Added Date</th>
                        <th class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    ${members.map(member => `
                        <tr>
                            <td class="font-mono" style="font-size: 0.875rem;">${member.publicId || member.keyId}</td>
                            <td>${member.addedDate}</td>
                            <td class="text-right">
                                <button class="btn btn-ghost btn-sm" onclick="Toast.success('Member removed')">${Icons.Trash} Remove</button>
                            </td>
                        </tr>
                    `).join('')}
                </tbody>
            </table>
        </div>
    `;
    
    document.getElementById('app').innerHTML = createLayout('console', 
        createPageTemplate('Group Members', `Managing members for group: ${params.groupId}`, content)
    );

    document.getElementById('addGroupMemberForm').onsubmit = (e) => {
        e.preventDefault();
        Toast.success('Member added successfully!');
        e.target.reset();
    };
}

// Keychains List
function renderKeychainsList() {
    const keychains = [
        { id: 'a1b2c3d4e5f607181920212223242526', name: 'Production Keychain', keys: 15, created: '2026-01-10' },
        { id: 'b2c3d4e5f60718192021222324252627', name: 'Development Keychain', keys: 8, created: '2026-01-15' },
    ];
    
    const content = `
        <div class="card" style="margin-bottom: 1.5rem;">
            <div class="card-content">
                <h3>Create Keychain</h3>
                <form id="createKeychainForm" style="margin-top: 1rem;">
                    <div class="form-group">
                        <label>Keychain Name</label>
                        <input type="text" name="name" placeholder="Owner Keychain" required>
                    </div>
                    <button type="submit" class="btn btn-primary">${Icons.Plus} Create Keychain</button>
                </form>
            </div>
        </div>
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Keychain ID</th>
                        <th>Name</th>
                        <th>Keys</th>
                        <th>Created</th>
                        <th class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    ${keychains.map(keychain => `
                        <tr>
                            <td class="font-mono" style="font-size: 0.875rem;">${keychain.id}</td>
                            <td><strong>${keychain.name}</strong></td>
                            <td>${keychain.keys}</td>
                            <td>${keychain.created}</td>
                            <td class="text-right">
                                <a href="/console/keychains/${keychain.id}/members" data-link class="btn btn-outline btn-sm">${Icons.Key} View Keys</a>
                            </td>
                        </tr>
                    `).join('')}
                </tbody>
            </table>
        </div>
    `;
    
    document.getElementById('app').innerHTML = createLayout('console', 
        createPageTemplate('Keychains', 'Manage keychains and their associated keys', content)
    );

    document.getElementById('createKeychainForm').onsubmit = (e) => {
        e.preventDefault();
        Toast.success('Keychain created successfully!');
        e.target.reset();
    };
}

// Keychain Members
function renderKeychainMembers(params) {
    const members = [
        { keyId: 'key_primary_001', addedDate: '2026-01-10' },
        { keyId: 'key_secondary_045', addedDate: '2026-01-12' },
    ];
    
    const content = `
        <div class="card" style="margin-bottom: 1.5rem;">
            <div class="card-content">
                <h3>Add Key to Keychain</h3>
                <form id="addKeychainMemberForm" style="margin-top: 1rem;">
                    <div class="form-group">
                        <label>Key ID (hex32 or apub_ format)</label>
                        <input type="text" name="keyId" placeholder="a1b2c3d4e5f607181920212223242526 or apub_8cd1a2b3c4d5e6f7" required>
                    </div>
                    <button type="submit" class="btn btn-primary">${Icons.Plus} Add Key</button>
                </form>
            </div>
        </div>
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Key ID</th>
                        <th>Added Date</th>
                        <th class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    ${members.map(member => `
                        <tr>
                            <td class="font-mono" style="font-size: 0.875rem;">${member.publicId || member.keyId}</td>
                            <td>${member.addedDate}</td>
                            <td class="text-right">
                                <button class="btn btn-ghost btn-sm" onclick="Toast.success('Key removed from keychain')">${Icons.Trash} Remove</button>
                            </td>
                        </tr>
                    `).join('')}
                </tbody>
            </table>
        </div>
    `;
    
    document.getElementById('app').innerHTML = createLayout('console', 
        createPageTemplate('Keychain Keys', `Managing keys in keychain: ${params.id}`, content)
    );

    document.getElementById('addKeychainMemberForm').onsubmit = (e) => {
        e.preventDefault();
        Toast.success('Key added to keychain!');
        e.target.reset();
    };
}

// Posts List
function renderPostsList() {
    const posts = [
        { id: 'a1b2c3d4e5f607181920212223242526', title: 'Welcome to CRE8.pw', status: 'published', created: '2026-01-15', authorKeyId: 'a1b2c3d4e5f607181920212223242526' },
        { id: 'b2c3d4e5f60718192021222324252627', title: 'Getting Started Guide', status: 'published', created: '2026-01-18', authorKeyId: 'a1b2c3d4e5f607181920212223242526' },
        { id: 'c3d4e5f6071819202122232425262728', title: 'Advanced Features', status: 'draft', created: '2026-01-20', authorKeyId: 'b2c3d4e5f60718192021222324252627' },
    ];
    
    const content = `
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Post ID</th>
                        <th>Title</th>
                        <th>Author Key ID</th>
                        <th>Status</th>
                        <th>Created</th>
                        <th class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    ${posts.map(post => `
                        <tr>
                            <td class="font-mono" style="font-size: 0.875rem;">${post.id}</td>
                            <td><strong>${post.title}</strong></td>
                            <td class="font-mono" style="font-size: 0.875rem;">${post.authorKeyId}</td>
                            <td>
                                <span class="badge ${post.status === 'published' ? 'badge-success' : 'badge-secondary'}">
                                    ${post.status}
                                </span>
                            </td>
                            <td>${post.created}</td>
                            <td class="text-right">
                                <a href="/console/posts/${post.id}" data-link class="btn btn-outline btn-sm">${Icons.Eye} View</a>
                            </td>
                        </tr>
                    `).join('')}
                </tbody>
            </table>
        </div>
    `;
    
    document.getElementById('app').innerHTML = createLayout('console', 
        createPageTemplate('Posts', 'Manage all posts and content', content)
    );
}

// Post Detail (Console)
function renderConsolePostDetail(params) {
    const content = `
        <div class="space-y-4">
            <div>
                <h3>Post Information</h3>
                <div style="margin-top: 1rem;">
                    <p><strong>Post ID:</strong> <span class="font-mono">${params.postId}</span></p>
                    <p><strong>Title:</strong> Welcome to CRE8.pw</p>
                    <p><strong>Status:</strong> <span class="badge badge-success">Published</span></p>
                    <p><strong>Created:</strong> 2026-01-15</p>
                    <p><strong>Author:</strong> owner@example.com</p>
                </div>
            </div>
            <div style="margin-top: 2rem;">
                <h3>Content</h3>
                <p style="margin-top: 1rem;">Lorem ipsum dolor sit amet, consectetur adipiscing elit. Sed do eiusmod tempor incididunt ut labore et dolore magna aliqua.</p>
            </div>
            <div style="margin-top: 2rem;">
                <h3>Access Control</h3>
                <div class="table-container" style="margin-top: 1rem;">
                    <table>
                        <thead>
                            <tr>
                                <th>Group</th>
                                <th>Permission</th>
                                <th>Granted</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>Team Alpha</td>
                                <td><span class="badge badge-secondary">VIEW (0x01)</span></td>
                                <td>2026-01-19</td>
                            </tr>
                            <tr>
                                <td>Beta Testers</td>
                                <td><span class="badge badge-secondary">VIEW + COMMENT (0x03)</span></td>
                                <td>2026-01-21</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="flex gap-2" style="margin-top: 1rem;">
                    <a href="/console/posts/${params.postId}/access/grant-group" data-link class="btn btn-outline">${Icons.Plus} Grant Group Access</a>
                    <a href="/console/posts/${params.postId}/access/revoke-group" data-link class="btn btn-ghost">${Icons.X} Revoke Group Access</a>
                </div>
            </div>
        </div>
    `;
    
    document.getElementById('app').innerHTML = createLayout('console', 
        createPageTemplate('Post Details', `Viewing post: ${params.postId}`, content)
    );
}

// Grant Group Access
function renderGrantGroupAccess(params) {
    const content = `
        <form id="grantAccessForm">
            <div class="code-block" style="margin-bottom: 1rem;">
                <pre>POST /console/posts/${params.postId}/access/grant-group</pre>
            </div>
            <div class="form-group">
                <label>Select Group</label>
                <select name="groupId" required>
                    <option value="">Choose a group...</option>
                    <option value="a1b2c3d4e5f607181920212223242526">Team Alpha</option>
                    <option value="b2c3d4e5f60718192021222324252627">Beta Testers</option>
                    <option value="c3d4e5f6071819202122232425262728">Premium Users</option>
                </select>
            </div>
            <div class="form-group">
                <label>Permission Mask</label>
                <select name="permission" required>
                    <option value="1">VIEW (0x01) - Read only</option>
                    <option value="3">INTERACT (0x03) - VIEW + COMMENT</option>
                    <option value="11">ADMIN (0x0B) - VIEW + COMMENT + MANAGE_ACCESS</option>
                </select>
                <p style="font-size: 0.75rem; color: var(--gray-600); margin-top: 0.25rem;">
                    Bitmasks: VIEW=0x01, COMMENT=0x02, MANAGE_ACCESS=0x08
                </p>
            </div>
            <div class="flex gap-2">
                <button type="submit" class="btn btn-primary">Grant Access</button>
                <a href="/console/posts/${params.postId}" data-link class="btn btn-ghost">Cancel</a>
            </div>
        </form>
    `;
    
    document.getElementById('app').innerHTML = createLayout('console', 
        createPageTemplate('Grant Group Access', `Grant access for post: ${params.postId}`, content)
    );
    
    document.getElementById('grantAccessForm').onsubmit = (e) => {
        e.preventDefault();
        Toast.success('Group access granted successfully!');
        setTimeout(() => router.navigate(`/console/posts/${params.postId}`), 1000);
    };
}

// Revoke Group Access
function renderRevokeGroupAccess(params) {
    const groups = [
        { id: 'a1b2c3d4e5f607181920212223242526', name: 'Team Alpha', permissionMask: 1, permissionDesc: 'VIEW' },
        { id: 'b2c3d4e5f60718192021222324252627', name: 'Beta Testers', permissionMask: 3, permissionDesc: 'VIEW + COMMENT' },
    ];
    
    const content = `
        <p style="margin-bottom: 1rem;">Select a group to revoke access from this post.</p>
        <div class="code-block" style="margin-bottom: 1rem;">
            <pre>POST /console/posts/${params.postId}/access/revoke-group</pre>
        </div>
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Group Name</th>
                        <th>Permission</th>
                        <th class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    ${groups.map(group => `
                        <tr>
                            <td><strong>${group.name}</strong></td>
                            <td><span class="badge badge-secondary">${group.permissionDesc} (0x${group.permissionMask.toString(16).toUpperCase()})</span></td>
                            <td class="text-right">
                                <button class="btn btn-ghost btn-sm" onclick="Toast.success('Access revoked successfully!'); setTimeout(() => router.navigate('/console/posts/${params.postId}'), 1000);">${Icons.X} Revoke</button>
                            </td>
                        </tr>
                    `).join('')}
                </tbody>
            </table>
        </div>
    `;
    
    document.getElementById('app').innerHTML = createLayout('console', 
        createPageTemplate('Revoke Group Access', `Revoke access for post: ${params.postId}`, content)
    );
}

// API Pages
function renderApiKeyExchange() {
    const content = `
        <div class="space-y-4">
            <div>
                <h3>API Endpoint</h3>
                <div class="code-block" style="margin-top: 1rem;">
                    <pre>POST /api/auth/exchange</pre>
                </div>
            </div>
            <div>
                <h3>Request Body</h3>
                <div class="code-block" style="margin-top: 1rem;">
                    <pre>{
  "public_id": "key_primary_001",
  "secret": "sk_live_xxxxxxxxxxxxx"
}</pre>
                </div>
            </div>
            <form id="apiExchangeForm">
                <div class="form-group">
                    <label>Public ID (apub_ format)</label>
                    <input type="text" name="publicId" value="apub_8cd1a2b3c4d5e6f7" required>
                </div>
                <div class="form-group">
                    <label>Secret</label>
                    <input type="password" name="secret" placeholder="sk_live_xxxxxxxxxxxxx" required>
                </div>
                <button type="submit" class="btn btn-primary">${Icons.Send} Send Request</button>
            </form>
            <div id="apiResponse"></div>
        </div>
    `;
    
    document.getElementById('app').innerHTML = createLayout('api', 
        createPageTemplate('API Key Exchange', 'Exchange API key for access token', content)
    );
    
    document.getElementById('apiExchangeForm').onsubmit = (e) => {
        e.preventDefault();
        document.getElementById('apiResponse').innerHTML = `
            <div style="margin-top: 1rem;">
                <h3>Response (200 OK)</h3>
                <div class="code-block" style="margin-top: 0.5rem;">
                    <pre>{
  "data": {
    "access_token": "eyJhbGciOiJSUzI1NiIsInR5cCI6IkpXVCJ9...",
    "refresh_token": "eyJhbGciOiJSUzI1NiIsInR5cCI6IkpXVCJ9...",
    "expires_in": 3600,
    "token_type": "Bearer"
  }
}</pre>
                </div>
            </div>
        `;
        Toast.success('API request successful!');
    };
}

function renderTokenRefresh() {
    const content = `
        <div class="space-y-4">
            <div>
                <h3>API Endpoint</h3>
                <div class="code-block" style="margin-top: 1rem;">
                    <pre>POST /api/auth/refresh</pre>
                </div>
            </div>
            <form id="refreshForm">
                <div class="form-group">
                    <label>Refresh Token</label>
                    <input type="text" name="refreshToken" placeholder="eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9..." required>
                </div>
                <button type="submit" class="btn btn-primary">${Icons.RefreshCw} Refresh Token</button>
            </form>
            <div id="refreshResponse"></div>
        </div>
    `;
    
    document.getElementById('app').innerHTML = createLayout('api', 
        createPageTemplate('Token Refresh', 'Refresh access token using refresh token', content)
    );
    
    document.getElementById('refreshForm').onsubmit = (e) => {
        e.preventDefault();
        document.getElementById('refreshResponse').innerHTML = `
            <div style="margin-top: 1rem;">
                <h3>Response (200 OK)</h3>
                <div class="code-block" style="margin-top: 0.5rem;">
                    <pre>{
  "data": {
    "access_token": "eyJhbGciOiJSUzI1NiIsInR5cCI6IkpXVCJ9...",
    "refresh_token": "eyJhbGciOiJSUzI1NiIsInR5cCI6IkpXVCJ9...",
    "expires_in": 3600,
    "token_type": "Bearer"
  }
}</pre>
                </div>
            </div>
        `;
        Toast.success('Token refreshed successfully!');
    };
}

function renderMintSecondaryKey(params) {
    const content = `
        <div class="space-y-4">
            <div>
                <h3>API Endpoint</h3>
                <div class="code-block" style="margin-top: 1rem;">
                    <pre>POST /api/keys/${params.authorKeyId}/secondary</pre>
                </div>
            </div>
            <form id="mintSecondaryForm">
            <div class="form-group">
                <label>Permissions (comma-separated, must be subset of parent)</label>
                <input type="text" name="permissions" placeholder="posts:create,posts:read,comments:write" required>
                <p style="font-size: 0.75rem; color: var(--gray-600); margin-top: 0.25rem;">
                    Available: keys:issue, posts:create, posts:read, comments:write, groups:read, keychains:manage, posts:access:manage
                </p>
            </div>
                <div class="form-group">
                    <label>Label</label>
                    <input type="text" name="label" placeholder="Secondary Key Label" required>
                </div>
                <button type="submit" class="btn btn-primary">Mint Secondary Key</button>
            </form>
            <div id="mintResponse"></div>
        </div>
    `;
    
    document.getElementById('app').innerHTML = createLayout('api', 
        createPageTemplate('Mint Secondary Key', `Create a secondary key from: ${params.authorKeyId}`, content)
    );
    
    document.getElementById('mintSecondaryForm').onsubmit = (e) => {
        e.preventDefault();
        document.getElementById('mintResponse').innerHTML = `
            <div style="margin-top: 1rem;">
                <h3>Response (201 Created)</h3>
                <div class="code-block" style="margin-top: 0.5rem;">
                    <pre>{
  "data": {
    "key_id": "b2c3d4e5f60718192021222324252627",
    "key_public_id": "apub_9de2b3c4d5e6f7g8",
    "key_secret": "sk_live_xxxxxxxxxxxxx",
    "permissions": ["${e.target.permissions.value.split(',').join('","')}"],
    "label": "${e.target.label.value}",
    "parent_key_id": "${params.authorKeyId}",
    "created_at": "2026-01-25T10:30:00Z"
  }
}</pre>
                </div>
            </div>
        `;
        Toast.success('Secondary key minted successfully!');
    };
}

function renderMintUseKey(params) {
    const content = `
        <div class="space-y-4">
            <div>
                <h3>API Endpoint</h3>
                <div class="code-block" style="margin-top: 1rem;">
                    <pre>POST /api/keys/${params.authorKeyId}/use</pre>
                </div>
            </div>
            <form id="mintUseForm">
                <div class="form-group">
                    <label>Permissions (comma-separated, cannot include posts:create or keys:issue)</label>
                    <input type="text" name="permissions" placeholder="posts:read,comments:write" required>
                    <p style="font-size: 0.75rem; color: var(--gray-600); margin-top: 0.25rem;">
                        Available: posts:read, comments:write, groups:read, keychains:manage, posts:access:manage
                        <br><strong>Forbidden:</strong> posts:create, keys:issue
                    </p>
                </div>
                <div class="form-group">
                    <label>Label</label>
                    <input type="text" name="label" placeholder="Use Key Label" required>
                </div>
                <div class="form-group">
                    <label>Use Count (optional)</label>
                    <input type="number" name="useCount" placeholder="10">
                </div>
                <div class="form-group">
                    <label>Device Limit (optional)</label>
                    <input type="number" name="deviceLimit" placeholder="3">
                </div>
                <button type="submit" class="btn btn-primary">Mint Use Key</button>
            </form>
            <div id="useResponse"></div>
        </div>
    `;
    
    document.getElementById('app').innerHTML = createLayout('api', 
        createPageTemplate('Mint Use Key', `Create a use key from: ${params.authorKeyId}`, content)
    );
    
    document.getElementById('mintUseForm').onsubmit = (e) => {
        e.preventDefault();
        document.getElementById('useResponse').innerHTML = `
            <div style="margin-top: 1rem;">
                <h3>Response (201 Created)</h3>
                <div class="code-block" style="margin-top: 0.5rem;">
                    <pre>{
  "data": {
    "key_id": "c3d4e5f6071819202122232425262728",
    "key_public_id": "apub_0ef3c4d5e6f7g8h9",
    "key_secret": "sk_live_xxxxxxxxxxxxx",
    "permissions": ["${e.target.permissions.value.split(',').join('","')}"],
    "label": "${e.target.label.value}",
    "use_count_limit": ${e.target.useCount.value || 'null'},
    "device_limit": ${e.target.deviceLimit.value || 'null'},
    "parent_key_id": "${params.authorKeyId}",
    "created_at": "2026-01-25T10:30:00Z"
  }
}</pre>
                </div>
            </div>
        `;
        Toast.success('Use key minted successfully!');
    };
}

function renderApiPosts() {
    const posts = [
        { id: 'a1b2c3d4e5f607181920212223242526', title: 'Welcome to CRE8.pw', author: 'owner@example.com' },
        { id: 'b2c3d4e5f60718192021222324252627', title: 'Getting Started Guide', author: 'owner@example.com' },
    ];
    
    const content = `
        <div class="space-y-4">
            <div>
                <h3>Create Post</h3>
                <form id="createPostForm" style="margin-top: 1rem;">
                    <div class="form-group">
                        <label>Title</label>
                        <input type="text" name="title" placeholder="Post Title" required>
                    </div>
                    <div class="form-group">
                        <label>Content</label>
                        <textarea name="content" placeholder="Post content..." required></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary">Create Post</button>
                </form>
            </div>
            <div class="card" style="margin-top: 2rem;">
                <div class="card-content">
                    <h3>Sharing Workflow</h3>
                    <p style="margin-top: 0.5rem; color: var(--gray-600);">Create post → Mint use key → Grant access → Recipient reads/comments.</p>
                    <ol style="margin-top: 1rem;" class="space-y-2">
                        <li><strong>1.</strong> Publish a post below.</li>
                        <li><strong>2.</strong> Mint a use key from an author key.</li>
                        <li><strong>3.</strong> Grant the use key access to this post.</li>
                        <li><strong>4.</strong> Share the use key so recipients can read and comment.</li>
                    </ol>
                </div>
            </div>
            <div style="margin-top: 2rem;">
                <h3>List Posts</h3>
                <div class="table-container" style="margin-top: 1rem;">
                    <table>
                        <thead>
                            <tr>
                                <th>Post ID</th>
                                <th>Title</th>
                                <th>Author</th>
                                <th class="text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${posts.map(post => `
                                <tr>
                                    <td class="font-mono" style="font-size: 0.875rem;">${post.id}</td>
                                    <td><strong>${post.title}</strong></td>
                                    <td>${post.author}</td>
                                    <td class="text-right">
                                        <a href="/api/posts/${post.id}" data-link class="btn btn-outline btn-sm">${Icons.Eye} View</a>
                                    </td>
                                </tr>
                            `).join('')}
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    `;
    
    document.getElementById('app').innerHTML = createLayout('api', 
        createPageTemplate('Posts', 'Create and list posts via API', content)
    );
    
    document.getElementById('createPostForm').onsubmit = (e) => {
        e.preventDefault();
        Toast.success('Post created successfully!');
    };
}

function renderApiPostDetail(params) {
    const grants = [
        { id: 'acc_934', targetType: 'group', targetId: 'grp_001', permission: 'read' },
        { id: 'acc_935', targetType: 'key', targetId: 'key_use_892', permission: 'write' },
    ];
    const content = `
        <div class="space-y-4">
            <div>
                <h3>Post Information</h3>
                <div style="margin-top: 1rem;">
                    <p><strong>Post ID:</strong> <span class="font-mono">${params.postId}</span></p>
                    <p><strong>Title:</strong> Welcome to CRE8.pw</p>
                    <p><strong>Author:</strong> owner@example.com</p>
                    <p><strong>Created:</strong> 2026-01-15</p>
                </div>
            </div>
            <div>
                <h3>Content</h3>
                <p style="margin-top: 1rem;">Lorem ipsum dolor sit amet, consectetur adipiscing elit. Sed do eiusmod tempor incididunt ut labore et dolore magna aliqua.</p>
            </div>
            <div>
                <h3>Access Grants</h3>
                <div class="table-container" style="margin-top: 1rem;">
                    <table>
                        <thead>
                            <tr>
                                <th>Access ID</th>
                                <th>Target</th>
                                <th>Permission</th>
                                <th class="text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${grants.map(grant => `
                                <tr>
                                    <td class="font-mono" style="font-size: 0.875rem;">${grant.id}</td>
                                    <td>${grant.targetType}: ${grant.targetId}</td>
                                    <td><span class="badge badge-secondary">${grant.permissionDesc} (0x${grant.permissionMask.toString(16).toUpperCase()})</span></td>
                                    <td class="text-right">
                                        <a href="/api/posts/${params.postId}/access/${grant.targetType}/${grant.targetId}" data-link class="btn btn-ghost btn-sm">${Icons.X} Revoke</a>
                                    </td>
                                </tr>
                            `).join('')}
                        </tbody>
                    </table>
                </div>
            </div>
            <div style="margin-top: 2rem;">
                <h3>Actions</h3>
                <div class="flex gap-2" style="margin-top: 1rem;">
                    <a href="/api/posts/${params.postId}/access" data-link class="btn btn-outline">${Icons.Plus} Grant Access</a>
                    <a href="/api/posts/${params.postId}/comments" data-link class="btn btn-outline">${Icons.MessageSquare} Comments</a>
                </div>
            </div>
        </div>
    `;
    
    document.getElementById('app').innerHTML = createLayout('api', 
        createPageTemplate('Post Details', `Viewing post: ${params.postId}`, content)
    );
}

function renderGrantAccess(params) {
    const content = `
        <form id="grantApiAccessForm">
            <div class="code-block" style="margin-bottom: 1rem;">
                <pre>POST /api/posts/${params.postId}/access</pre>
            </div>
            <div class="form-group">
                <label>Target Type</label>
                <select name="targetType" required>
                    <option value="key">key</option>
                    <option value="group">group</option>
                </select>
            </div>
                <div class="form-group">
                <label>Target ID (hex32 or apub_ format)</label>
                <input type="text" name="targetId" placeholder="a1b2c3d4e5f607181920212223242526 or apub_8cd1a2b3c4d5e6f7" required>
            </div>
            <div class="form-group">
                <label>Permission Mask</label>
                <select name="permission" required>
                    <option value="1">VIEW (0x01) - Read only</option>
                    <option value="3">INTERACT (0x03) - VIEW + COMMENT</option>
                    <option value="11">ADMIN (0x0B) - VIEW + COMMENT + MANAGE_ACCESS</option>
                </select>
                <p style="font-size: 0.75rem; color: var(--gray-600); margin-top: 0.25rem;">
                    Bitmasks: VIEW=0x01, COMMENT=0x02, MANAGE_ACCESS=0x08
                </p>
            </div>
            <button type="submit" class="btn btn-primary">Grant Access</button>
        </form>
        <div id="grantApiAccessResponse" style="margin-top: 1rem;"></div>
    `;
    
    document.getElementById('app').innerHTML = createLayout('api', 
        createPageTemplate('Grant Access', `Grant access to post: ${params.postId}`, content)
    );
    
    document.getElementById('grantApiAccessForm').onsubmit = (e) => {
        e.preventDefault();
        Toast.success('Access granted successfully!');
        document.getElementById('grantApiAccessResponse').innerHTML = `
            <div class="code-block">
                <pre>{
  "data": {
    "access_id": "a1b2c3d4e5f607181920212223242526",
    "target_type": "${e.target.targetType.value}",
    "target_id": "${e.target.targetId.value}",
    "permission_mask": ${e.target.permission.value},
    "post_id": "${params.postId}",
    "created_at": "2026-01-25T10:30:00Z"
  }
}</pre>
            </div>
        `;
        setTimeout(() => router.navigate(`/api/posts/${params.postId}`), 1000);
    };
}

function renderRevokeAccess(params) {
    const grants = [
        { id: 'acc_934', targetType: 'group', targetId: 'grp_001', permission: 'read' },
        { id: 'acc_935', targetType: 'key', targetId: 'key_use_892', permission: 'write' },
    ];

    const content = `
        <p style="margin-bottom: 1rem;">Select an access grant to revoke.</p>
        <div class="code-block" style="margin-bottom: 1rem;">
            <pre>DELETE /api/posts/${params.postId}/access/${params.targetType}/${params.targetId}</pre>
        </div>
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Access ID</th>
                        <th>Target</th>
                        <th>Permission</th>
                        <th class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    ${grants.map(grant => `
                        <tr>
                            <td class="font-mono" style="font-size: 0.875rem;">${grant.id}</td>
                            <td>${grant.targetType}: ${grant.targetId}</td>
                            <td><span class="badge badge-secondary">${grant.permissionDesc} (0x${grant.permissionMask.toString(16).toUpperCase()})</span></td>
                            <td class="text-right">
                                <button class="btn btn-ghost btn-sm" onclick="Toast.success('Access revoked successfully!'); setTimeout(() => router.navigate('/api/posts/${params.postId}'), 1000);">${Icons.X} Revoke</button>
                            </td>
                        </tr>
                    `).join('')}
                </tbody>
            </table>
        </div>
    `;
    
    document.getElementById('app').innerHTML = createLayout('api', 
        createPageTemplate('Revoke Access', `Revoke access for post: ${params.postId}`, content)
    );
}

function renderComments(params) {
    const comments = [
        { id: 1, author: 'user@example.com', text: 'Great post!', time: '2 hours ago' },
        { id: 2, author: 'another@example.com', text: 'Very helpful, thanks!', time: '5 hours ago' },
    ];
    
    const content = `
        <div class="space-y-4">
            <div>
                <h3>Add Comment</h3>
                <form id="addCommentForm" style="margin-top: 1rem;">
                    <div class="form-group">
                        <label>Comment</label>
                        <textarea name="comment" placeholder="Your comment..." required></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary">${Icons.Send} Add Comment</button>
                </form>
            </div>
            <div style="margin-top: 2rem;">
                <h3>Comments</h3>
                <div style="margin-top: 1rem;">
                    ${comments.map(comment => `
                        <div class="card" style="margin-bottom: 1rem;">
                            <div class="card-content">
                                <div class="flex justify-between" style="margin-bottom: 0.5rem;">
                                    <strong>${comment.author}</strong>
                                    <span style="font-size: 0.875rem; color: var(--gray-500);">${comment.time}</span>
                                </div>
                                <p>${comment.text}</p>
                            </div>
                        </div>
                    `).join('')}
                </div>
            </div>
        </div>
    `;
    
    document.getElementById('app').innerHTML = createLayout('api', 
        createPageTemplate('Comments', `Comments for post: ${params.postId}`, content)
    );
    
    document.getElementById('addCommentForm').onsubmit = (e) => {
        e.preventDefault();
        Toast.success('Comment added successfully!');
        e.target.reset();
    };
}

function renderUseKeyFeed(params) {
    const posts = [
        { id: 'a1b2c3d4e5f607181920212223242526', title: 'Welcome to CRE8.pw', date: '2026-01-15' },
        { id: 'b2c3d4e5f60718192021222324252627', title: 'Getting Started Guide', date: '2026-01-18' },
    ];
    
    const content = `
        <div class="space-y-4">
            <div>
                <h3>Feed for Use Key: ${params.useKeyId}</h3>
                <p style="margin-top: 0.5rem; color: var(--gray-600);">Posts accessible with this use key</p>
            </div>
            <div class="card">
                <div class="card-content">
                    <h4>Pagination</h4>
                    <p style="margin-top: 0.5rem; color: var(--gray-600);">Use <code>before_id</code> for older posts or <code>since_id</code> for newer posts as you scroll.</p>
                </div>
            </div>
            <div class="grid grid-cols-2 gap-4">
                ${posts.map(post => createCard(post.title, `
                    <p style="font-size: 0.875rem; color: var(--gray-600); margin-bottom: 1rem;">Published: ${post.date}</p>
                    <a href="/api/posts/${post.id}" data-link class="btn btn-outline btn-sm">View Post</a>
                `)).join('')}
            </div>
        </div>
    `;
    
    document.getElementById('app').innerHTML = createLayout('api', 
        createPageTemplate('Use-Key Feed', 'Posts accessible with this use key', content)
    );
}

function renderAuthorFeed() {
    const posts = [
        { id: 'a1b2c3d4e5f607181920212223242526', title: 'Welcome to CRE8.pw', views: 245, date: '2026-01-15' },
        { id: 'b2c3d4e5f60718192021222324252627', title: 'Getting Started Guide', views: 189, date: '2026-01-18' },
        { id: 'c3d4e5f6071819202122232425262728', title: 'Advanced Features', views: 134, date: '2026-01-20' },
    ];
    
    const content = `
        <div class="space-y-4">
            <div>
                <h3>Author Feed</h3>
                <p style="margin-top: 0.5rem; color: var(--gray-600);">All posts by the authenticated author</p>
            </div>
            <div class="card">
                <div class="card-content">
                    <strong>Coming soon</strong>
                    <p style="margin-top: 0.5rem; color: var(--gray-600);">Schema placeholders for future author feed APIs will appear here.</p>
                </div>
            </div>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Post ID</th>
                            <th>Title</th>
                            <th>Views</th>
                            <th>Date</th>
                            <th class="text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${posts.map(post => `
                            <tr>
                                <td class="font-mono" style="font-size: 0.875rem;">${post.id}</td>
                                <td><strong>${post.title}</strong></td>
                                <td>${post.views}</td>
                                <td>${post.date}</td>
                                <td class="text-right">
                                    <a href="/api/posts/${post.id}" data-link class="btn btn-outline btn-sm">${Icons.Eye} View</a>
                                </td>
                            </tr>
                        `).join('')}
                    </tbody>
                </table>
            </div>
        </div>
    `;
    
    document.getElementById('app').innerHTML = createLayout('api', 
        createPageTemplate('Author Feed', 'All posts by authenticated author', content)
    );
}

function renderApiGroupsList() {
    const groups = [
        { id: 'a1b2c3d4e5f607181920212223242526', name: 'Team Alpha', members: 12 },
        { id: 'b2c3d4e5f60718192021222324252627', name: 'Beta Testers', members: 8 },
        { id: 'c3d4e5f6071819202122232425262728', name: 'Premium Users', members: 45 },
    ];
    
    const content = `
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Group ID</th>
                        <th>Name</th>
                        <th>Members</th>
                        <th class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    ${groups.map(group => `
                        <tr>
                            <td class="font-mono" style="font-size: 0.875rem;">${group.id}</td>
                            <td><strong>${group.name}</strong></td>
                            <td>${group.members}</td>
                            <td class="text-right">
                                <a href="/api/groups/${group.id}" data-link class="btn btn-outline btn-sm">${Icons.Eye} View</a>
                                <a href="/api/groups/${group.id}/members" data-link class="btn btn-ghost btn-sm">${Icons.Users} Members</a>
                            </td>
                        </tr>
                    `).join('')}
                </tbody>
            </table>
        </div>
    `;
    
    document.getElementById('app').innerHTML = createLayout('api', 
        createPageTemplate('Groups (Read-Only)', 'View groups via API', content)
    );
}

function renderApiGroupDetail(params) {
    const content = `
        <div class="space-y-4">
            <div>
                <h3>Group Information</h3>
                <div style="margin-top: 1rem;">
                    <p><strong>Group ID:</strong> <span class="font-mono">${params.groupId}</span></p>
                    <p><strong>Name:</strong> Team Alpha</p>
                    <p><strong>Members:</strong> 12</p>
                    <p><strong>Created:</strong> 2026-01-10</p>
                </div>
            </div>
            <div>
                <h3>Members</h3>
                <a href="/api/groups/${params.groupId}/members" data-link class="btn btn-outline" style="margin-top: 0.5rem;">View Members</a>
            </div>
            <div style="margin-top: 2rem;">
                <h3>API Response</h3>
                <div class="code-block" style="margin-top: 1rem;">
                    <pre>{
  "data": {
    "group_id": "${params.groupId}",
    "name": "Team Alpha",
    "member_count": 12,
    "created_at": "2026-01-10T00:00:00Z"
  }
}</pre>
                </div>
            </div>
        </div>
    `;
    
    document.getElementById('app').innerHTML = createLayout('api', 
        createPageTemplate('Group Details', `Viewing group: ${params.groupId}`, content)
    );
}

function renderApiGroupMembers(params) {
    const members = [
        { keyId: 'key_primary_001', addedDate: '2026-01-10' },
        { keyId: 'key_secondary_045', addedDate: '2026-01-12' },
        { keyId: 'key_use_892', addedDate: '2026-01-15' },
    ];
    
    const content = `
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Key ID</th>
                        <th>Added Date</th>
                    </tr>
                </thead>
                <tbody>
                    ${members.map(member => `
                        <tr>
                            <td class="font-mono" style="font-size: 0.875rem;">${member.keyId}</td>
                            <td>${member.addedDate}</td>
                        </tr>
                    `).join('')}
                </tbody>
            </table>
        </div>
    `;
    
    document.getElementById('app').innerHTML = createLayout('api', 
        createPageTemplate('Group Members', `Members of group: ${params.groupId}`, content)
    );
}

function renderApiKeychainCreate() {
    const content = `
        <div class="space-y-4">
            <div>
                <h3>Create External Keychain</h3>
                <p style="margin-top: 0.5rem; color: var(--gray-600);">Create a new keychain via API</p>
            </div>
            <form id="createKeychainForm">
                <div class="form-group">
                    <label>Keychain Name</label>
                    <input type="text" name="name" placeholder="My Keychain" required>
                </div>
                <div class="form-group">
                    <label>Description</label>
                    <textarea name="description" placeholder="Optional description"></textarea>
                </div>
                <button type="submit" class="btn btn-primary">Create Keychain</button>
            </form>
            <div id="keychainResponse"></div>
        </div>
    `;
    
    document.getElementById('app').innerHTML = createLayout('api', 
        createPageTemplate('Create Keychain', 'Create a new external keychain', content)
    );
    
    document.getElementById('createKeychainForm').onsubmit = (e) => {
        e.preventDefault();
        const keychainId = 'a1b2c3d4e5f607181920212223242526';
        document.getElementById('keychainResponse').innerHTML = `
            <div style="margin-top: 1rem;">
                <h3>Response (201 Created)</h3>
                <div class="code-block" style="margin-top: 0.5rem;">
                    <pre>{
  "data": {
    "keychain_id": "${keychainId}",
    "name": "${e.target.name.value}",
    "created_at": "2026-01-25T10:30:00Z"
  }
}</pre>
                </div>
                <div class="flex gap-2" style="margin-top: 1rem;">
                    <a href="/api/keychains/${keychainId}/members" data-link class="btn btn-outline">${Icons.Key} Manage Members</a>
                </div>
            </div>
        `;
        Toast.success('Keychain created successfully!');
    };
}

function renderApiKeychainMembers(params) {
    const members = [
        { keyId: 'key_primary_001', addedDate: '2026-01-10' },
        { keyId: 'key_secondary_045', addedDate: '2026-01-12' },
    ];
    
    const content = `
        <div class="space-y-4">
            <div>
                <h3>Add Key to Keychain</h3>
                <form id="addKeyForm" style="margin-top: 1rem;">
                    <div class="form-group">
                        <label>Key ID (hex32 or apub_ format)</label>
                        <input type="text" name="keyId" placeholder="a1b2c3d4e5f607181920212223242526 or apub_8cd1a2b3c4d5e6f7" required>
                    </div>
                    <button type="submit" class="btn btn-primary">${Icons.Plus} Add Key</button>
                </form>
            </div>
            <div style="margin-top: 2rem;">
                <h3>Current Keys</h3>
                <div class="table-container" style="margin-top: 1rem;">
                    <table>
                        <thead>
                            <tr>
                                <th>Key ID</th>
                                <th>Added Date</th>
                                <th class="text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${members.map(member => `
                                <tr>
                                    <td class="font-mono" style="font-size: 0.875rem;">${member.keyId}</td>
                                    <td>${member.addedDate}</td>
                                    <td class="text-right">
                                        <button class="btn btn-ghost btn-sm" onclick="Toast.success('Key removed from keychain')">${Icons.Trash} Remove</button>
                                    </td>
                                </tr>
                            `).join('')}
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    `;
    
    document.getElementById('app').innerHTML = createLayout('api', 
        createPageTemplate('Keychain Members', `Managing keys in keychain: ${params.id}`, content)
    );
    
    document.getElementById('addKeyForm').onsubmit = (e) => {
        e.preventDefault();
        Toast.success('Key added to keychain!');
        e.target.reset();
    };
}

// ============================================
// Route Configuration
// ============================================
router.addRoute('/', renderLanding);
router.addRoute('/console/register', renderOwnerRegister);
router.addRoute('/console/login', renderOwnerLogin);
router.addRoute('/console/dashboard', renderDashboard);
router.addRoute('/console/keys', renderKeysList);
router.addRoute('/console/keys/primary', renderMintPrimaryKey);
router.addRoute('/console/keys/:keyId', renderKeyDetail);
router.addRoute('/console/keys/:keyId/lineage', renderKeyLineage);
router.addRoute('/console/keys/:keyId/rotate', renderRotateKey);
router.addRoute('/console/keys/:keyId/activate', renderActivateKey);
router.addRoute('/console/keys/:keyId/deactivate', renderDeactivateKey);
router.addRoute('/console/groups', renderGroupsList);
router.addRoute('/console/groups/:groupId', renderGroupDetail);
router.addRoute('/console/groups/:groupId/rename', renderRenameGroup);
router.addRoute('/console/groups/:groupId/members', renderGroupMembers);
router.addRoute('/console/keychains', renderKeychainsList);
router.addRoute('/console/keychains/:id/members', renderKeychainMembers);
router.addRoute('/console/posts', renderPostsList);
router.addRoute('/console/posts/:postId', renderConsolePostDetail);
router.addRoute('/console/posts/:postId/access/grant-group', renderGrantGroupAccess);
router.addRoute('/console/posts/:postId/access/revoke-group', renderRevokeGroupAccess);
router.addRoute('/api/auth/exchange', renderApiKeyExchange);
router.addRoute('/api/auth/refresh', renderTokenRefresh);
router.addRoute('/api/keys/:authorKeyId/secondary', renderMintSecondaryKey);
router.addRoute('/api/keys/:authorKeyId/use', renderMintUseKey);
router.addRoute('/api/posts', renderApiPosts);
router.addRoute('/api/posts/:postId', renderApiPostDetail);
router.addRoute('/api/posts/:postId/access', renderGrantAccess);
router.addRoute('/api/posts/:postId/access/:targetType/:targetId', renderRevokeAccess);
router.addRoute('/api/posts/:postId/comments', renderComments);
router.addRoute('/api/feed/use/:useKeyId', renderUseKeyFeed);
router.addRoute('/api/feed/author', renderAuthorFeed);
router.addRoute('/api/groups', renderApiGroupsList);
router.addRoute('/api/groups/:groupId', renderApiGroupDetail);
router.addRoute('/api/groups/:groupId/members', renderApiGroupMembers);
router.addRoute('/api/keychains', renderApiKeychainCreate);
router.addRoute('/api/keychains/:id/members', renderApiKeychainMembers);

function handleRedirectFrom404() {
    const redirectPath = sessionStorage.getItem('redirectPath');
    if (redirectPath) {
        sessionStorage.removeItem('redirectPath');
        window.history.replaceState({}, '', redirectPath);
    }
}

// Start the application
handleRedirectFrom404();
router.start();
