# CRE8.pw - Updated UI Demonstration

This is an updated vanilla HTML, CSS, and JavaScript implementation of the CRE8.pw credentialing and authorization platform. This version has been updated to accurately reflect the actual CRE8.pw codebase, including correct permissions, key types, ID formats, API endpoints, and response structures.

## 📦 What's Included

- **index.html** - Main HTML file
- **styles.css** - Complete CSS styling with custom design system
- **app.js** - Full application logic with client-side routing (updated to match CRE8.pw backend)
- **icons.js** - SVG icon library
- **404.html** - 404 redirect page
- **DOWNLOAD.html** - Download instructions page

## 🚀 Quick Start

### Option 1: Local File System
1. Download all files to a folder on your computer
2. Open `index.html` directly in your web browser
3. That's it! The application will run completely in your browser

### Option 2: Local Web Server (Recommended)
For better routing support, use a simple local web server:

**Using Python 3:**
```bash
python -m http.server 8000
```

**Using Python 2:**
```bash
python -m SimpleHTTPServer 8000
```

**Using Node.js:**
```bash
npx http-server
```

**Using PHP:**
```bash
php -S localhost:8000
```

Then open your browser to `http://localhost:8000`

## ✨ Updates in This Version

This UI demonstration has been updated to accurately reflect the CRE8.pw platform:

### Key Terminology
- **Primary Author Key** - Root-level keys owned by Owners
- **Secondary Author Key** - Keys minted from Primary Author Keys
- **Use Key** - Limited keys for accessing posts (cannot have `posts:create` or `keys:issue` permissions)

### ID Formats
- **Internal IDs**: `hex32` format (32-character lowercase hexadecimal, e.g., `a1b2c3d4e5f607181920212223242526`)
- **Public IDs**: `apub_` format (e.g., `apub_8cd1a2b3c4d5e6f7`)

### Permissions
- **Owner Permissions**: `owners:manage`, `keys:issue`, `keys:read`, `keys:rotate`, `keys:state:update`, `groups:manage`, `keychains:manage`, `posts:admin:read`, `posts:access:manage`
- **Key Permissions**: `keys:issue`, `posts:create`, `posts:read`, `comments:write`, `groups:read`, `keychains:manage`, `posts:access:manage`
- **Use Key Restrictions**: Cannot include `posts:create` or `keys:issue`

### Post Access Bitmasks
- **VIEW (0x01)** - Read-only access
- **COMMENT (0x02)** - Ability to comment
- **MANAGE_ACCESS (0x08)** - Ability to manage access grants
- **INTERACT (0x03)** - VIEW + COMMENT
- **ADMIN (0x0B)** - VIEW + COMMENT + MANAGE_ACCESS

### API Response Format
All API responses follow the standard envelope format:
```json
{
  "data": {
    // Response data here
  }
}
```

Error responses:
```json
{
  "error": {
    // Error details here
  }
}
```

### API Endpoints
- Console routes match `config/routes/console_json.php`
- Gateway routes match `config/routes/gateway_json.php`
- Post access revocation uses `/api/posts/:postId/access/:targetType/:targetId` format

## 📋 Features

### Owner Console (`/console/*` routes)
- **Dashboard** - Overview with statistics and quick actions
- **Key Management** - Create, view, and manage keys
  - Mint primary keys with comma-separated permissions
  - View key details and lineage
  - Rotate, activate, and deactivate keys
- **Groups** - Organize keys into groups
  - Create and manage groups
  - Add/remove group members (using hex32 or apub_ IDs)
  - Rename groups
- **Keychains** - Manage keychain collections
  - View all keychains
  - Manage keychain members
- **Posts** - Content management
  - List all posts
  - View post details
  - Grant/revoke group access using bitmask permissions

### API Client (`/api/*` routes)
- **Authentication**
  - API key exchange (using apub_ format)
  - Token refresh
- **Key Issuance**
  - Mint secondary keys (permissions must be subset of parent)
  - Mint use keys (with restrictions on forbidden permissions)
- **Posts & Access**
  - Create and list posts
  - View post details
  - Grant/revoke access using bitmask permissions
  - Comment system
- **Feeds**
  - Use-key feed
  - Author feed
- **Groups (Read-Only)**
  - List groups
  - View group details
  - View group members
- **External Keychains**
  - Create keychains
  - Manage keychain members

## 🎨 Design System

The application uses a custom design system with:
- Modern, clean interface
- Responsive grid layouts
- Card-based components
- Toast notifications for user feedback
- Color-coded status badges
- Syntax-highlighted code blocks for API examples

## 🔧 Customization

### Colors
Edit the CSS variables in `styles.css` under the `:root` selector to change the color scheme:

```css
:root {
    --blue-600: #2563eb;
    --green-600: #16a34a;
    --purple-600: #9333ea;
    /* ... more colors */
}
```

### Adding New Pages
1. Create a render function in `app.js`:
```javascript
function renderMyNewPage() {
    const content = `<div>My new page content</div>`;
    document.getElementById('app').innerHTML = createLayout('console', 
        createPageTemplate('My Page Title', 'Description', content)
    );
}
```

2. Add a route:
```javascript
router.addRoute('/my-new-page', renderMyNewPage);
```

### Mock Data
All data in this demo is mock data stored in JavaScript arrays. To connect to a real backend:

1. Replace the mock data with API calls
2. Use `fetch()` to call your backend API
3. Update the form handlers to send real data

Example:
```javascript
fetch('https://your-api.com/keys')
    .then(response => response.json())
    .then(data => {
        // Use the data to render your page
    });
```

## 📱 Browser Support

Works in all modern browsers:
- Chrome/Edge (latest)
- Firefox (latest)
- Safari (latest)
- Opera (latest)

## 🌐 Deployment

### GitHub Pages
1. Create a new repository on GitHub
2. Upload all files
3. Go to Settings → Pages
4. Select "Deploy from branch" and choose your main branch
5. Your site will be live at `https://yourusername.github.io/repository-name/`

### Netlify
1. Drag and drop the folder to [Netlify Drop](https://app.netlify.com/drop)
2. Your site is instantly deployed!

### Any Web Server
Simply upload all files to your web server's public directory.

## 📖 How It Works

### Client-Side Routing
The application uses a custom JavaScript router that:
- Listens for clicks on links with `data-link` attribute
- Updates the browser URL without page reload
- Matches URL patterns (including dynamic parameters like `:keyId`)
- Renders the appropriate page component

### Component System
Pages are built using:
- `createPageTemplate()` - Standard page wrapper
- `createLayout()` - Sidebar navigation layout
- `createCard()` - Reusable card components
- Template strings for dynamic HTML generation

### Toast Notifications
User feedback is provided via toast notifications:
```javascript
Toast.success('Operation successful!');
Toast.error('Something went wrong!');
Toast.show('Custom message', 'info');
```

## 🔒 Security Note

This is a **frontend demonstration** with mock data. For production use:
- Implement proper backend authentication
- Add CSRF protection
- Validate all inputs server-side
- Use HTTPS
- Store sensitive data securely
- Implement rate limiting
- Add proper error handling

## 💡 Tips

- The navigation sections are collapsible - click the section headers
- Use the "Switch to API Client" / "Switch to Console" links to navigate between the two main sections
- All forms include basic validation and show success toasts
- The application remembers your navigation state
- Works offline once loaded (no external dependencies)
- Key IDs can be entered in either hex32 or apub_ format
- Permission inputs accept comma-separated permission strings
- Post access uses bitmask values (0x01, 0x03, 0x0B) for fine-grained control

## 📝 License

This is a demonstration application. Feel free to use and modify as needed for your projects.

## 🤝 Support

For questions or issues with the CRE8.pw platform, please refer to the main CRE8.pw documentation.

---

**Note:** This updated version accurately reflects the CRE8.pw backend implementation, including correct permission strings, key type terminology, ID formats, API endpoints, and response structures. Perfect for understanding the platform architecture and testing UI concepts!
