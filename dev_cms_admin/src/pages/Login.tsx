import { useEffect } from 'react'
import { useNavigate } from 'react-router-dom'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { z } from 'zod'
import { toast } from 'sonner'
import { User, Lock, Shield } from 'lucide-react'
import { useAuth, useCaptcha } from '@/hooks'
import { getErrorMessage } from '@/lib/utils'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Checkbox } from '@/components/ui/checkbox'
import {
  Form,
  FormControl,
  FormField,
  FormItem,
  FormLabel,
  FormMessage,
} from '@/components/ui/form'

const loginBaseSchema = z.object({
  username: z.string().min(1, '请输入用户名'),
  password: z.string().min(1, '请输入密码'),
  captcha: z.string().optional(),
  rememberMe: z.boolean().optional(),
})

type LoginFormValues = z.infer<typeof loginBaseSchema>

export default function Login() {
  const auth = useAuth()
  const captcha = useCaptcha()
  const navigate = useNavigate()
  const loginSchema = loginBaseSchema.refine(
    (data) => !captcha.enabled || (data.captcha != null && data.captcha.trim().length > 0),
    { message: '请输入验证码', path: ['captcha'] }
  )
  const form = useForm<LoginFormValues>({
    resolver: zodResolver(loginSchema),
    defaultValues: { username: '', password: '', captcha: '', rememberMe: false },
  })

  useEffect(() => {
    if (auth.isAuthenticated) {
      navigate('/console', { replace: true })
    }
  }, [auth.isAuthenticated, navigate])

  const handleLogin = async (values: LoginFormValues) => {
    try {
      await auth.login(values)
      toast.success('登录成功')
    } catch (err) {
      toast.error(getErrorMessage(err, '登录失败'))
      if (captcha.enabled) {
        await captcha.refresh()
      }
    }
  }

  return (
    <div className="flex min-h-screen items-center justify-center bg-background p-6">
      <Card className="w-full max-w-[400px] shadow-lg">
        <CardHeader>
          <CardTitle className="text-center text-2xl font-semibold text-primary">
            AnonEcho
          </CardTitle>
        </CardHeader>
        <CardContent>
          <Form {...form}>
            <form onSubmit={form.handleSubmit(handleLogin)} className="space-y-4">
              <FormField
                control={form.control}
                name="username"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>用户名</FormLabel>
                    <FormControl>
                      <div className="relative">
                        <User className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                        <Input
                          {...field}
                          className="pl-9"
                          placeholder="用户名"
                          autoComplete="username"
                        />
                      </div>
                    </FormControl>
                    <FormMessage />
                  </FormItem>
                )}
              />
              <FormField
                control={form.control}
                name="password"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>密码</FormLabel>
                    <FormControl>
                      <div className="relative">
                        <Lock className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                        <Input
                          {...field}
                          type="password"
                          className="pl-9"
                          placeholder="密码"
                          autoComplete="current-password"
                        />
                      </div>
                    </FormControl>
                    <FormMessage />
                  </FormItem>
                )}
              />
              {captcha.loading ? (
                <p className="text-center text-sm text-muted-foreground">加载验证码配置...</p>
              ) : captcha.enabled ? (
                <FormField
                  control={form.control}
                  name="captcha"
                  render={({ field }) => (
                    <FormItem>
                      <FormLabel>验证码</FormLabel>
                      <FormControl>
                        <div className="flex gap-2">
                          <div className="relative flex-1">
                            <Shield className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                            <Input
                              {...field}
                              className="pl-9"
                              placeholder="验证码"
                              autoComplete="off"
                            />
                          </div>
                          {captcha.image ? (
                            <img
                              src={captcha.image}
                              alt="验证码"
                              role="button"
                              tabIndex={0}
                              onClick={captcha.refresh}
                              onKeyDown={(e) => e.key === 'Enter' && captcha.refresh()}
                              className="h-10 w-[100px] cursor-pointer rounded-md border border-input object-cover"
                            />
                          ) : (
                            <div className="flex h-10 w-[100px] items-center justify-center rounded-md border border-input text-xs text-muted-foreground">
                              加载中...
                            </div>
                          )}
                        </div>
                      </FormControl>
                      <FormMessage />
                    </FormItem>
                  )}
                />
              ) : null}
              <FormField
                control={form.control}
                name="rememberMe"
                render={({ field }) => (
                  <FormItem className="flex flex-row items-center space-x-2 space-y-0">
                    <FormControl>
                      <Checkbox
                        checked={field.value}
                        onCheckedChange={field.onChange}
                      />
                    </FormControl>
                    <FormLabel className="font-normal">记住我</FormLabel>
                  </FormItem>
                )}
              />
              <Button type="submit" className="w-full" disabled={auth.loading}>
                {auth.loading ? '登录中...' : '登录'}
              </Button>
            </form>
          </Form>
        </CardContent>
      </Card>
    </div>
  )
}
