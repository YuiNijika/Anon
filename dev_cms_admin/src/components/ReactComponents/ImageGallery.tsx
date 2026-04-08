import { Card, CardContent } from '@/components/ui/card'
import { Lightbox } from '@/components/ui/lightbox'
import { useState } from 'react'

interface ImageGalleryProps {
  images: string[]
  columns?: number
  gap?: number
}

export function ImageGallery({ 
  images = [], 
  columns = 3,
  gap = 16
}: ImageGalleryProps) {
  const [lightboxOpen, setLightboxOpen] = useState(false)
  const [lightboxSrc, setLightboxSrc] = useState('')

  if (!images || images.length === 0) {
    return (
      <Card>
        <CardContent className="py-8 text-center text-muted-foreground">
          暂无图片
        </CardContent>
      </Card>
    )
  }

  const gridCols = {
    1: 'grid-cols-1',
    2: 'grid-cols-2',
    3: 'grid-cols-3',
    4: 'grid-cols-4',
  }[columns] || 'grid-cols-3'

  return (
    <>
      <div className={`grid ${gridCols} gap-${gap}`}>
        {images.map((src, index) => (
          <button
            key={index}
            type="button"
            className="relative aspect-square overflow-hidden rounded-lg cursor-zoom-in bg-muted group"
            onClick={() => {
              setLightboxSrc(src)
              setLightboxOpen(true)
            }}
          >
            <img
              src={src}
              alt={`Image ${index + 1}`}
              className="h-full w-full object-cover transition-transform group-hover:scale-110"
              loading="lazy"
            />
            <div className="absolute inset-0 bg-black/0 group-hover:bg-black/20 transition-colors" />
          </button>
        ))}
      </div>

      <Lightbox
        open={lightboxOpen}
        onOpenChange={setLightboxOpen}
        src={lightboxSrc}
      />
    </>
  )
}
