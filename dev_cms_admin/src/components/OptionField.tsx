import { useState, useMemo } from 'react'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Switch } from '@/components/ui/switch'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group'
import { ToggleGroup } from '@/components/ui/toggle-group'
import { Slider } from '@/components/ui/slider'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert'
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
  AlertDialogTrigger,
} from '@/components/ui/alert-dialog'
import { Separator } from '@/components/ui/separator'
import { Result } from '@/components/ui/result'
import { Card, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible'
import { Tooltip, TooltipContent, TooltipTrigger, TooltipProvider } from '@/components/ui/tooltip'
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'
import type {
  ThemeOptionSchema,
  ThemeOptionAlertAction,
  ThemeOptionTreeNode,
  ThemeOptionDescItem,
  ThemeOptionTableColumn,
} from '@/services/admin'
import { cn } from '@/lib/utils'
import { buildPublicUrl } from '@/utils/api'
import MediaLibrary from '@/components/MediaLibrary'
import { ChevronDown, X, ArrowRight, ArrowLeft, Upload, HelpCircle, Plus } from 'lucide-react'

const desc = (description?: string) =>
  description ? <span className="ml-1 font-normal text-muted-foreground">({description})</span> : null

function flattenTree(nodes: ThemeOptionTreeNode[]): { value: string; label: string; depth: number }[] {
  const out: { value: string; label: string; depth: number }[] = []
  function walk(items: ThemeOptionTreeNode[], d: number) {
    items.forEach((n) => {
      out.push({ value: n.value, label: n.label, depth: d })
      if (n.children?.length) walk(n.children, d + 1)
    })
  }
  walk(nodes, 0)
  return out
}

function UploadField({
  label,
  description,
  value,
  onChange,
  accept,
  multiple,
}: {
  label: string
  description?: string
  value: string
  onChange: (v: unknown) => void
  accept?: string
  multiple?: boolean
}) {
  const [mediaLibraryOpen, setMediaLibraryOpen] = useState(false)
  const handleSelect = (attachment: any) => {
    const url = attachment?.insert_url ?? attachment?.url
    if (typeof url === 'string') onChange(buildPublicUrl(url))
    setMediaLibraryOpen(false)
  }
  return (
    <div className="space-y-2">
      <Label>{label}{desc(description)}</Label>
      <div className="flex gap-2 items-center">
        <Input
          value={value}
          onChange={(e) => onChange(e.target.value)}
          placeholder="图片或文件 URL，或从媒体库选择"
          className="flex-1"
        />
        <Button
          type="button"
          variant="outline"
          size="sm"
          onClick={() => setMediaLibraryOpen(true)}
        >
          <Upload className="h-4 w-4 mr-1" /> 媒体库
        </Button>
      </div>
      <MediaLibrary
        open={mediaLibraryOpen}
        onClose={() => setMediaLibraryOpen(false)}
        onSelect={handleSelect}
        accept={accept}
        multiple={multiple}
      />
    </div>
  )
}

function TextListField({
  label,
  description,
  placeholder,
  value,
  onChange,
}: {
  label: string
  description?: string
  placeholder: string
  value: string[]
  onChange: (list: string[]) => void
}) {
  const [draft, setDraft] = useState('')
  const updateList = (next: string[]) => onChange(next)
  const addItem = () => {
    const v = draft.trim()
    const next = v ? [...value, v] : [...value, '']
    updateList(next)
    setDraft('')
  }
  return (
    <div className="space-y-2">
      <Label>{label}{desc(description)}</Label>
      <div className="space-y-2">
        {value.map((item, i) => (
          <div key={i} className="flex gap-2 items-center">
            <Input
              value={item}
              onChange={(e) => {
                const next = [...value]
                next[i] = e.target.value
                // 实时更新，不过滤，让用户可以编辑
                onChange(next)
              }}
              placeholder={placeholder}
              className="flex-1"
            />
            <Button
              type="button"
              variant="outline"
              size="icon"
              className="shrink-0"
              onClick={() => updateList(value.filter((_, j) => j !== i))}
              title="删除此项"
            >
              <X className="h-4 w-4" />
            </Button>
          </div>
        ))}
        <div className="flex gap-2 items-center">
          <Input
            value={draft}
            onChange={(e) => setDraft(e.target.value)}
            onKeyDown={(e) => e.key === 'Enter' && (e.preventDefault(), addItem())}
            placeholder={placeholder}
            className="flex-1"
          />
          <Button
            type="button"
            variant="outline"
            size="icon"
            className="shrink-0"
            onClick={addItem}
            title="+1 新增一项"
          >
            <Plus className="h-4 w-4" />
          </Button>
        </div>
      </div>
    </div>
  )
}

function AutocompleteField({
  name,
  label,
  description,
  options,
  value,
  onChange,
}: {
  name: string
  label: string
  description?: string
  options: Record<string, string>
  value: string
  onChange: (v: unknown) => void
}) {
  const [open, setOpen] = useState(false)
  const [keyword, setKeyword] = useState(value)
  const optsEntries = Object.entries(options)
  const filtered = useMemo(
    () =>
      !keyword.trim()
        ? optsEntries
        : optsEntries.filter(
          ([k, v]) =>
            v.toLowerCase().includes(keyword.toLowerCase()) ||
            k.toLowerCase().includes(keyword.toLowerCase())
        ),
    [keyword, optsEntries]
  )
  return (
    <div className="space-y-2 relative">
      <Label>{label}{desc(description)}</Label>
      <div className="relative">
        <Input
          id={name}
          value={keyword}
          onChange={(e) => {
            setKeyword(e.target.value)
            onChange(e.target.value)
            setOpen(true)
          }}
          onFocus={() => setOpen(true)}
          onBlur={() => setTimeout(() => setOpen(false), 150)}
          placeholder="输入并选择"
        />
        {open && filtered.length > 0 && (
          <div className="absolute z-50 top-full left-0 right-0 mt-1 rounded-md border bg-popover shadow-md py-1 max-h-60 overflow-auto">
            {filtered.slice(0, 20).map(([k, v]) => (
              <button
                key={k}
                type="button"
                className="w-full px-3 py-2 text-left text-sm hover:bg-muted"
                onMouseDown={() => {
                  onChange(k)
                  setKeyword(v)
                  setOpen(false)
                }}
              >
                {v}
              </button>
            ))}
          </div>
        )}
      </div>
    </div>
  )
}

/** 可关闭的 Notice */
function NoticeField({ option }: { name: string; option: ThemeOptionSchema }) {
  const [dismissed, setDismissed] = useState(false)
  if (dismissed) return null
  const variant = (option.variant as 'default' | 'destructive' | 'success' | 'warning') ?? 'default'
  const canClose = option.dismissible !== false
  return (
    <Alert variant={variant} className="relative pr-10">
      <AlertTitle>{option.label}</AlertTitle>
      {(option as any).message && <AlertDescription>{(option as any).message}</AlertDescription>}
      {option.description && <AlertDescription>{option.description}</AlertDescription>}
      {canClose && (
        <Button
          type="button"
          variant="ghost"
          size="icon"
          className="absolute right-2 top-2 h-6 w-6"
          aria-label="关闭"
          onClick={() => setDismissed(true)}
        >
          <X className="h-4 w-4" />
        </Button>
      )}
    </Alert>
  )
}

export interface OptionFieldProps {
  name: string
  option: ThemeOptionSchema
  value?: unknown
  onChange?: (value: unknown) => void
}

export function OptionField({ name, option, value, onChange }: OptionFieldProps) {
  const { type, label, description, options: opts, default: def } = option
  const setValue = (v: unknown) => onChange?.(v)

  switch (type) {
    case 'text':
      return (
        <div className="space-y-2">
          <Label htmlFor={name}>{label}{desc(description)}</Label>
          <Input
            id={name}
            value={(value as string) ?? ''}
            onChange={(e) => setValue(e.target.value)}
            placeholder={String(def ?? '')}
            className="w-full"
          />
        </div>
      )
    case 'textarea':
      return (
        <div className="space-y-2">
          <Label htmlFor={name}>{label}{desc(description)}</Label>
          <textarea
            id={name}
            rows={4}
            value={(value as string) ?? ''}
            onChange={(e) => setValue(e.target.value)}
            placeholder={String(def ?? '')}
            className="flex min-h-[80px] w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
          />
        </div>
      )
    case 'select':
      return (
        <div className="space-y-2">
          <Label>{label}{desc(description)}</Label>
          <Select value={(value as string) ?? ''} onValueChange={(v) => setValue(v)}>
            <SelectTrigger><SelectValue placeholder="请选择" /></SelectTrigger>
            <SelectContent>
              {Object.entries(opts || {}).map(([k, v]) => (
                <SelectItem key={k} value={k}>{v}</SelectItem>
              ))}
            </SelectContent>
          </Select>
        </div>
      )
    case 'checkbox':
      return (
        <div className="flex items-center justify-between space-x-2">
          <Label htmlFor={name} className="flex-1">{label}{desc(description)}</Label>
          <Switch id={name} checked={!!value} onCheckedChange={(c) => setValue(c)} />
        </div>
      )
    case 'number': {
      const numVal: string | number =
        typeof value === 'number' ? value
          : value !== undefined && value !== null ? String(value)
            : ''
      const min = (option as any).min
      const max = (option as any).max
      const step = (option as any).step
      return (
        <div className="space-y-2">
          <Label htmlFor={name}>{label}{desc(description)}</Label>
          <Input
            id={name}
            type="number"
            value={numVal}
            min={min}
            max={max}
            step={step}
            onChange={(e) => { const v = e.target.value; setValue(v === '' ? undefined : Number(v)) }}
            placeholder={String(def ?? '')}
            className="w-full"
          />
        </div>
      )
    }
    case 'text_list': {
      const list = Array.isArray(value) ? (value as string[]) : (value ? [String(value)] : [])
      const placeholder = (option as any).listPlaceholder ?? '输入后点击右侧 +1 新增一项'
      return (
        <TextListField
          name={name}
          label={label}
          description={description}
          placeholder={placeholder}
          value={list}
          onChange={setValue}
        />
      )
    }
    case 'color':
      return (
        <div className="space-y-2">
          <Label htmlFor={name}>{label}{desc(description)}</Label>
          <div className="flex items-center gap-2">
            <input
              type="color"
              id={name}
              value={typeof value === 'string' && value.startsWith('#') ? value : '#000000'}
              onChange={(e) => setValue(e.target.value)}
              className="h-10 w-14 cursor-pointer rounded border border-input bg-background p-1"
            />
            <Input
              value={(value as string) ?? ''}
              onChange={(e) => setValue(e.target.value)}
              placeholder="#000000"
              className="flex-1 font-mono text-sm"
            />
          </div>
        </div>
      )
    case 'date':
      return (
        <div className="space-y-2">
          <Label htmlFor={name}>{label}{desc(description)}</Label>
          <Input
            id={name}
            type="date"
            value={(value as string) ?? ''}
            onChange={(e) => setValue(e.target.value)}
            className="w-full"
          />
        </div>
      )
    case 'time':
      return (
        <div className="space-y-2">
          <Label htmlFor={name}>{label}{desc(description)}</Label>
          <Input
            id={name}
            type="time"
            value={(value as string) ?? ''}
            onChange={(e) => setValue(e.target.value)}
            className="w-full"
          />
        </div>
      )
    case 'datetime':
      return (
        <div className="space-y-2">
          <Label htmlFor={name}>{label}{desc(description)}</Label>
          <Input
            id={name}
            type="datetime-local"
            value={(value as string) ?? ''}
            onChange={(e) => setValue(e.target.value)}
            className="w-full"
          />
        </div>
      )
    case 'radio':
      return (
        <div className="space-y-2">
          <Label>{label}{desc(description)}</Label>
          <RadioGroup
            value={(value as string) ?? ''}
            onValueChange={(v) => setValue(v)}
            className="flex flex-wrap gap-4"
          >
            {Object.entries(opts || {}).map(([k, v]) => (
              <div key={k} className="flex items-center space-x-2">
                <RadioGroupItem value={k} id={`${name}-${k}`} />
                <Label htmlFor={`${name}-${k}`} className="font-normal cursor-pointer">{v}</Label>
              </div>
            ))}
          </RadioGroup>
        </div>
      )
    case 'button_group':
      return (
        <div className="space-y-2">
          <Label>{label}{desc(description)}</Label>
          <ToggleGroup
            value={(value as string) ?? ''}
            onValueChange={(v) => setValue(v)}
            options={opts || {}}
          />
        </div>
      )
    case 'slider':
      return (
        <div className="space-y-2">
          <Label>{label}{desc(description)}</Label>
          <Slider
            min={option.min ?? 0}
            max={option.max ?? 100}
            step={option.step ?? 1}
            value={typeof value === 'number' ? value : Number(value) || 0}
            onValueChange={(v) => setValue(v)}
          />
        </div>
      )
    case 'badge':
      return (
        <div className="flex items-center gap-2">
          <Badge variant={(option.variant as any) ?? 'secondary'}>
            {(option as any).text ?? label}
          </Badge>
          {description && <span className="text-sm text-muted-foreground">{description}</span>}
        </div>
      )
    case 'divider':
      return <Separator className="my-4" />
    case 'alert': {
      const actions = (option.actions || []) as ThemeOptionAlertAction[]
      const alertEl = (
        <Alert variant={(option.variant as any) ?? 'default'}>
          <AlertTitle>{label}</AlertTitle>
          {(option as any).message && <AlertDescription>{(option as any).message}</AlertDescription>}
          {description && <AlertDescription>{description}</AlertDescription>}
          {actions.length > 0 && (
            <div className="mt-3 flex flex-wrap gap-2">
              {actions.map((a, i) =>
                a.dialog ? (
                  <AlertDialog key={i}>
                    <AlertDialogTrigger asChild>
                      <Button type="button" variant="outline" size="sm">{a.label}</Button>
                    </AlertDialogTrigger>
                    <AlertDialogContent>
                      <AlertDialogHeader>
                        <AlertDialogTitle>{a.dialogTitle ?? a.label}</AlertDialogTitle>
                        {a.dialogMessage && <AlertDialogDescription>{a.dialogMessage}</AlertDialogDescription>}
                      </AlertDialogHeader>
                      <AlertDialogFooter>
                        <AlertDialogCancel>关闭</AlertDialogCancel>
                      </AlertDialogFooter>
                    </AlertDialogContent>
                  </AlertDialog>
                ) : (
                  <Button key={i} type="button" variant="outline" size="sm">{a.label}</Button>
                )
              )}
            </div>
          )}
        </Alert>
      )
      return alertEl
    }
    case 'notice':
      return <NoticeField name={name} option={option} />
    case 'alert_dialog': {
      const btnText = (option as any).buttonText ?? '查看详情'
      const dTitle = (option as any).dialogTitle ?? label
      const dDesc = (option as any).dialogDescription ?? (option as any).message ?? description
      const confirmText = (option as any).dialogConfirmText ?? '确定'
      return (
        <div className="space-y-2">
          <Alert variant={(option.variant as any) ?? 'default'}>
            <AlertTitle>{label}</AlertTitle>
            {(option as any).message && <AlertDescription>{(option as any).message}</AlertDescription>}
            {description && <AlertDescription>{description}</AlertDescription>}
            <div className="mt-3">
              <AlertDialog>
                <AlertDialogTrigger asChild>
                  <Button type="button" variant="outline" size="sm">{btnText}</Button>
                </AlertDialogTrigger>
                <AlertDialogContent>
                  <AlertDialogHeader>
                    <AlertDialogTitle>{dTitle}</AlertDialogTitle>
                    {dDesc && <AlertDialogDescription>{dDesc}</AlertDialogDescription>}
                  </AlertDialogHeader>
                  <AlertDialogFooter>
                    <AlertDialogCancel>关闭</AlertDialogCancel>
                    <AlertDialogAction>{confirmText}</AlertDialogAction>
                  </AlertDialogFooter>
                </AlertDialogContent>
              </AlertDialog>
            </div>
          </Alert>
        </div>
      )
    }
    case 'content':
      return (
        <div className="rounded-md border border-transparent bg-muted/50 px-3 py-2 text-sm text-muted-foreground">
          {(option as any).message ?? option.description ?? label}
        </div>
      )
    case 'heading': {
      const level = (option.level ?? 3) as 2 | 3 | 4
      const className = cn('font-semibold tracking-tight', level === 2 && 'text-lg', level === 3 && 'text-base', level === 4 && 'text-sm')
      if (level === 2) return <h2 className={className}>{label}</h2>
      if (level === 4) return <h4 className={className}>{label}</h4>
      return <h3 className={className}>{label}</h3>
    }
    case 'accordion':
      return (
        <Collapsible className="rounded-md border">
          <CollapsibleTrigger className="flex w-full items-center justify-between px-4 py-3 text-left font-medium hover:bg-muted/50 [&[data-state=open]>svg]:rotate-180">
            {label}
            <ChevronDown className="h-4 w-4 shrink-0 transition-transform" />
          </CollapsibleTrigger>
          <CollapsibleContent>
            <div className="border-t px-4 py-3 text-sm text-muted-foreground">
              {(option as any).message ?? description ?? ''}
            </div>
          </CollapsibleContent>
        </Collapsible>
      )
    case 'result':
      return (
        <Result
          status={(option.status as any) ?? 'empty'}
          title={(option as any).title ?? label}
          description={(option as any).description ?? description}
        />
      )
    case 'card':
      return (
        <Card>
          <CardHeader className="pb-2">
            <CardTitle className="text-base">{(option as any).title ?? label}</CardTitle>
            {((option as any).description ?? description) && (
              <CardDescription>{(option as any).description ?? description}</CardDescription>
            )}
          </CardHeader>
        </Card>
      )
    case 'tree_select': {
      const treeData = (option as any).treeData as ThemeOptionTreeNode[] | undefined
      const flat = treeData?.length ? flattenTree(treeData) : Object.entries(opts || {}).map(([value, label]) => ({ value, label, depth: 0 }))
      return (
        <div className="space-y-2">
          <Label>{label}{desc(description)}</Label>
          <Select value={(value as string) ?? ''} onValueChange={(v) => setValue(v)}>
            <SelectTrigger><SelectValue placeholder="请选择" /></SelectTrigger>
            <SelectContent>
              {flat.map(({ value: v, label: l, depth }) => (
                <SelectItem key={v} value={v}>
                  <span style={{ marginLeft: depth * 12 }}>{l}</span>
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
        </div>
      )
    }
    case 'transfer': {
      const transferOpts = (option as any).transferOptions as Record<string, string> | undefined
      const choices = transferOpts || opts || {}
      const selected = (Array.isArray(value) ? value : value ? [value] : []) as string[]
      const left = Object.keys(choices).filter((k) => !selected.includes(k))
      const add = (k: string) => setValue([...selected, k])
      const remove = (k: string) => setValue(selected.filter((s) => s !== k))
      return (
        <div className="space-y-2">
          <Label>{label}{desc(description)}</Label>
          <div className="flex gap-2 items-stretch">
            <div className="flex-1 rounded-md border bg-muted/30 p-2 min-h-[120px]">
              <p className="text-xs text-muted-foreground mb-2">可选</p>
              <div className="flex flex-wrap gap-1">
                {left.map((k) => (
                  <Badge key={k} variant="outline" className="cursor-pointer" onClick={() => add(k)}>
                    {choices[k]} <ArrowRight className="inline h-3 w-3 ml-0.5" />
                  </Badge>
                ))}
              </div>
            </div>
            <div className="flex-1 rounded-md border bg-muted/30 p-2 min-h-[120px]">
              <p className="text-xs text-muted-foreground mb-2">已选</p>
              <div className="flex flex-wrap gap-1">
                {selected.map((k) => (
                  <Badge key={k} variant="secondary" className="cursor-pointer" onClick={() => remove(k)}>
                    <ArrowLeft className="inline h-3 w-3 mr-0.5" /> {choices[k] ?? k}
                  </Badge>
                ))}
              </div>
            </div>
          </div>
        </div>
      )
    }
    case 'upload':
      return (
        <UploadField
          label={label}
          description={description}
          value={(value as string) ?? ''}
          onChange={setValue}
          accept={(option as any).uploadAccept}
          multiple={!!(option as any).uploadMultiple}
        />
      )
    case 'description_list': {
      const items = (option as any).descItems as ThemeOptionDescItem[] | undefined
      if (!items?.length) return null
      return (
        <div className="space-y-2">
          {label && <Label>{label}</Label>}
          <dl className="rounded-md border divide-y text-sm">
            {items.map((item, i) => (
              <div key={i} className="flex px-3 py-2">
                <dt className="w-28 shrink-0 font-medium text-muted-foreground">{item.label}</dt>
                <dd className="min-w-0 flex-1">{item.value}</dd>
              </div>
            ))}
          </dl>
        </div>
      )
    }
    case 'virtual_select':
      return (
        <div className="space-y-2">
          <Label>{label}{desc(description)}</Label>
          <Select value={(value as string) ?? ''} onValueChange={(v) => setValue(v)}>
            <SelectTrigger><SelectValue placeholder="请选择" /></SelectTrigger>
            <SelectContent>
              {Object.entries(opts || {}).map(([k, v]) => (
                <SelectItem key={k} value={k}>{v}</SelectItem>
              ))}
            </SelectContent>
          </Select>
        </div>
      )
    case 'table': {
      const cols = (option as any).tableColumns as ThemeOptionTableColumn[] | undefined
      const rows = (option as any).tableRows as Record<string, unknown>[] | undefined
      if (!cols?.length) return null
      return (
        <div className="space-y-2">
          {label && <Label>{label}</Label>}
          <div className="rounded-md border overflow-auto max-h-[300px]">
            <Table>
              <TableHeader>
                <TableRow>
                  {cols.map((c) => (
                    <TableHead key={c.key}>{c.title}</TableHead>
                  ))}
                </TableRow>
              </TableHeader>
              <TableBody>
                {(rows || []).map((row, i) => (
                  <TableRow key={i}>
                    {cols.map((c) => (
                      <TableCell key={c.key}>{String(row[c.key] ?? '-')}</TableCell>
                    ))}
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          </div>
        </div>
      )
    }
    case 'tooltip':
      return (
        <div className="flex items-center gap-2">
          <TooltipProvider>
            <Tooltip>
              <TooltipTrigger asChild>
                <span className="inline-flex items-center gap-1 cursor-help border-b border-dashed border-muted-foreground">
                  {label}
                  <HelpCircle className="h-3.5 w-3.5 text-muted-foreground" />
                </span>
              </TooltipTrigger>
              <TooltipContent>
                <p className="max-w-xs">{(option as any).tooltipContent ?? description ?? ''}</p>
              </TooltipContent>
            </Tooltip>
          </TooltipProvider>
          {description && <span className="text-sm text-muted-foreground">{description}</span>}
        </div>
      )
    case 'tag': {
      const tags = (option as any).tags as string[] | undefined
      const list = Array.isArray(tags) ? tags : [(option as any).text ?? label]
      return (
        <div className="flex flex-wrap items-center gap-2">
          {list.map((t, i) => (
            <Badge key={i} variant={(option.variant as any) ?? 'secondary'}>{t}</Badge>
          ))}
          {description && <span className="text-sm text-muted-foreground">{description}</span>}
        </div>
      )
    }
    case 'autocomplete':
      return (
        <AutocompleteField
          name={name}
          label={label}
          description={description}
          options={opts || {}}
          value={(value as string) ?? ''}
          onChange={setValue}
        />
      )
    default:
      return null
  }
}
