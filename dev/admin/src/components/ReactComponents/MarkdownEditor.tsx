import MDEditor from '@uiw/react-md-editor'

interface MarkdownEditorProps {
  placeholder?: string
  height?: string
  preview?: boolean
}

export function MarkdownEditor({ 
  placeholder = '开始写作...', 
  height = '400px',
  preview = true
}: MarkdownEditorProps) {
  const minHeight = parseInt(height) || 400
  
  return (
    <div className="markdown-editor-wrapper">
      <MDEditor
        preview={preview ? 'edit' : 'live'}
        height={minHeight}
        textareaProps={{
          placeholder: placeholder,
        }}
      />
    </div>
  )
}
