import React from 'react';
import ReactDOM from 'react-dom/client';
import App from './App';
import AdminPanel from './AdminPanel';

const root = ReactDOM.createRoot(document.getElementById('root'));
const isAdmin = window.location.pathname.replace(/\/$/, '') === '/admin';

root.render(
  <React.StrictMode>
    {isAdmin ? <AdminPanel /> : <App />}
  </React.StrictMode>
);
