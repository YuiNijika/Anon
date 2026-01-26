import { Navigate } from 'react-router-dom'
import Layout from '@/layouts/Layout'
import Login from '@/pages/Login'
import Console from '@/pages/Console'
import SettingsBasic from '@/pages/SettingsBasic'
import SettingsTheme from '@/pages/SettingsTheme'
import Statistics from '@/pages/Statistics'

export const routes = [
  {
    path: '/login',
    element: <Login />,
  },
  {
    path: '/',
    element: <Layout />,
    children: [
      {
        index: true,
        element: <Navigate to="/console" replace />,
      },
      {
        path: 'console',
        element: <Console />,
      },
      {
        path: 'statistics',
        element: <Statistics />,
      },
      {
        path: 'settings/basic',
        element: <SettingsBasic />,
      },
      {
        path: 'settings/theme',
        element: <SettingsTheme />,
      },
    ],
  },
]

