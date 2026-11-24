import React from 'react';
import { createRoot } from 'react-dom/client';
import { createInertiaApp } from '@inertiajs/react';
import Home from './Pages/Home.jsx';
import Sobre from './Pages/Sobre.jsx';
import Login from './Pages/Auth/Login.jsx';
import Register from './Pages/Auth/Register.jsx';
import Dashboard from './Pages/Dashboard.jsx';
import DepartmentIndex from './Pages/Departments/Index.jsx';
import DepartmentForm from './Pages/Departments/Form.jsx';
import EmployeeIndex from './Pages/Employees/Index.jsx';
import EmployeeForm from './Pages/Employees/Form.jsx';
import EmployeeImport from './Pages/Employees/Import.jsx';
import PatternIndex from './Pages/WorkPatterns/Index.jsx';
import PatternForm from './Pages/WorkPatterns/Form.jsx';
import PatternAssignments from './Pages/WorkPatterns/Assignments.jsx';

const pages = { Home, Sobre, 'Auth/Login': Login, 'Auth/Register': Register, Dashboard, 'Departments/Index': DepartmentIndex, 'Departments/Form': DepartmentForm, 'Employees/Index': EmployeeIndex, 'Employees/Form': EmployeeForm, 'Employees/Import': EmployeeImport, 'WorkPatterns/Index': PatternIndex, 'WorkPatterns/Form': PatternForm, 'WorkPatterns/Assignments': PatternAssignments };
createInertiaApp({
  resolve: name => {
    if (!pages[name]) throw new Error(`Página desconhecida: ${name}`);
    return pages[name];
  },
  setup: ({ el, App, props }) => createRoot(el).render(<App {...props} />),
});
