import React from 'react';
import ReactDOM from 'react-dom/client';
import App from './App';
import AdminPanel from './AdminPanel';

const root = ReactDOM.createRoot(document.getElementById('root'));

// GitHub Pages serves this project under /bangladesh-voter-app.
// Support both /admin and /bangladesh-voter-app/admin.
const path = window.location.pathname.replace(/\/$/, '');
const isAdmin = path === '/admin' || path.endsWith('/bangladesh-voter-app/admin');

root.render(
  <React.StrictMode>
    {isAdmin ? <AdminPanel /> : <App />}
  </React.StrictMode>
);
