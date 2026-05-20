# Frontend Asset & API URL Configuration Guide

To resolve broken image rendering and improve architectural scalability, we are decoupling our API and Asset (Image) base URLs. Please follow these steps to update the frontend.

## 1. Environment Configuration

Update your frontend `.env` file (located in the project root) to include separate base URLs:

```env
# URL for all API/Data requests (e.g., student/read.php)
VITE_API_URL=http://localhost/sitIn/api

# URL for all static assets (e.g., uploaded images)
VITE_ASSET_URL=http://localhost/sitIn
```

## 2. Recommended Implementation

We recommend centralizing these variables in a configuration file (e.g., `src/config.js` or `src/constants.js`) to avoid importing `import.meta.env` repeatedly.

**Example `src/config.js`:**
```javascript
export const API_URL = import.meta.env.VITE_API_URL;
export const ASSET_URL = import.meta.env.VITE_ASSET_URL;
```

## 3. Usage Pattern

Update your components to use the appropriate constant depending on the context:

### API Requests
```javascript
import { API_URL } from './config';

// Example: Fetching student data
fetch(`${API_URL}/student/read.php`)
  .then(res => res.json())
  .then(data => console.log(data));
```

### Image Rendering
Database paths for images now point directly to the project root (e.g., `uploads/profiles/profile_123.png`). Simply prepend the `ASSET_URL`:

```javascript
import { ASSET_URL } from './config';

// In your JSX
<img 
  src={`${ASSET_URL}/${student.profile_pic}`} 
  alt="Profile" 
  onError={(e) => e.target.style.display = 'none'} 
/>
```

## Why this change?
- **Stability:** Hardcoded path logic (like `.replace('api/', '')`) is brittle. This approach explicitly separates data traffic from static file traffic.
- **Scalability:** If we move images to a CDN in the future, we only need to update `VITE_ASSET_URL` in one place.
- **Correctness:** This resolves the 404 errors by ensuring static images are not incorrectly prefixed with the `/api/` path segment.
