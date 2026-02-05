import { useNavigate, useLocation } from 'react-router-dom'
import { Button } from '@/components/ui/button'

export default function ErrorPage() {
  const navigate = useNavigate()
  const location = useLocation()

  let errorMessage = 'Page Not Found'
  let errorStatus = 404

  if (location.pathname === '/error') {
    errorMessage = 'Access Denied: You do not have permission to access this page.'
    errorStatus = 403
  }

  return (
    <div className="flex min-h-[60vh] flex-col items-center justify-center gap-4">
      <h1 className="text-4xl font-bold">{errorStatus}</h1>
      <p className="text-lg text-muted-foreground">
        {errorMessage}
      </p>
      <div className="flex gap-2">
        <Button onClick={() => navigate(-1)} variant="outline">
          Go Back
        </Button>
        <Button onClick={() => navigate('/')}>
          Go Home
        </Button>
      </div>
    </div>
  )
}
