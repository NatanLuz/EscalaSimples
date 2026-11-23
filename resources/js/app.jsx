import React from 'react';
import { createRoot } from 'react-dom/client';
import { createInertiaApp } from '@inertiajs/react';
import Home from './Pages/Home.jsx';
import Sobre from './Pages/Sobre.jsx';

const pages = { Home, Sobre };
createInertiaApp({
  resolve: name => {
    if (!pages[name]) throw new Error(`Página desconhecida: ${name}`);
    return pages[name];
  },
  setup: ({ el, App, props }) => createRoot(el).render(<App {...props} />),
});
