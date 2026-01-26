import { Navigate } from 'react-router-dom'
import Layout from '@/layouts/Layout'
import Login from '@/pages/Login'
import Console from '@/pages/Console'
import SettingsBasic from '@/pages/SettingsBasic'
import SettingsTheme from '@/pages/SettingsTheme'
import Statistics from '@/pages/Statistics'
import Write from '@/pages/Write'
import ManageCategories from '@/pages/ManageCategories'
import ManageTags from '@/pages/ManageTags'
import ManageFiles from '@/pages/ManageFiles'
import ManagePosts from '@/pages/ManagePosts'

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
        path: 'write',
        element: <Write />,
      },
      {
        path: 'manage/categories',
        element: <ManageCategories />,
      },
      {
        path: 'manage/tags',
        element: <ManageTags />,
      },
      {
        path: 'manage/files',
        element: <ManageFiles />,
      },
      {
        path: 'manage/posts',
        element: <ManagePosts />,
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

