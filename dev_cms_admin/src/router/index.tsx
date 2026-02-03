import { Navigate } from 'react-router-dom'
import Layout from '@/layouts/Layout'
import Login from '@/pages/Login'
import Console from '@/pages/Console'
import SettingsBasic from '@/pages/Settings/Basic'
import SettingsPage from '@/pages/Settings/Page'
import SettingsPermission from '@/pages/Settings/Permission'
import Statistics from '@/pages/Statistics'
import Write from '@/pages/Write'
import Themes from '@/pages/Themes'
import Plugins from '@/pages/Plugins'
import ManageCategories from '@/pages/Manage/Categories'
import ManageTags from '@/pages/Manage/Tags'
import ManageFiles from '@/pages/Manage/Files'
import ManagePosts from '@/pages/Manage/Posts'
import ManageUsers from '@/pages/Manage/Users'
import ManageComments from '@/pages/Manage/Comments'

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
        path: 'plugins',
        element: <Plugins />,
      },
      {
        path: 'themes',
        element: <Themes />,
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
        path: 'manage/users',
        element: <ManageUsers />,
      },
      {
        path: 'manage/comments',
        element: <ManageComments />,
      },
      {
        path: 'settings/basic',
        element: <SettingsBasic />,
      },
      {
        path: 'settings/page',
        element: <SettingsPage />,
      },
      {
        path: 'settings/permission',
        element: <SettingsPermission />,
      },
    ],
  },
]

