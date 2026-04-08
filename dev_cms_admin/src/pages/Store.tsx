import { useState, useEffect } from 'react';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter } from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import { ItemGrid, type ItemCardData } from '@/components/ItemCard';
import { useStore, type StoreItem } from '@/hooks/useStore';
import { Download, Loader2, Github } from 'lucide-react';
import { toast } from 'sonner';
import ReactMarkdown from 'react-markdown';

export function Store() {
  const [activeTab, setActiveTab] = useState<'theme' | 'plugin'>('theme');
  const [items, setItems] = useState<StoreItem[]>([]);
  const [loading, setLoading] = useState(true);
  const [downloading, setDownloading] = useState<string | null>(null);
  const [detailDialogOpen, setDetailDialogOpen] = useState(false);
  const [selectedItem, setSelectedItem] = useState<StoreItem | null>(null);
  const [detailData, setDetailData] = useState<any>(null);
  const [detailLoading, setDetailLoading] = useState(false);
  const { getItems, downloadItem, getDetail } = useStore();

  useEffect(() => {
    loadItems();
  }, [activeTab]);

  const loadItems = async () => {
    try {
      setLoading(true);
      const data = await getItems(activeTab);
      setItems(data.items || []);
    } catch (error) {
      console.error('获取列表失败:', error);
      toast.error('获取列表失败');
    } finally {
      setLoading(false);
    }
  };

  const handleViewDetail = async (item: ItemCardData) => {
    setSelectedItem(item as StoreItem);
    setDetailDialogOpen(true);
    setDetailLoading(true);
    setDetailData(null);
    
    try {
      const data = await getDetail(item as StoreItem);
      setDetailData(data);
    } catch (error) {
      console.error('获取详情失败:', error);
      toast.error('获取详情失败');
    } finally {
      setDetailLoading(false);
    }
  };

  const handleDownload = async () => {
    if (!selectedItem || !selectedItem.url?.github) return;
    
    try {
      setDownloading(selectedItem.name);
      // 构建下载 URL
      const downloadUrl = selectedItem.url.github + '/archive/refs/heads/main.zip';
      
      const result = await downloadItem(selectedItem.name, selectedItem.type, downloadUrl);
      
      toast.success(result.message || '安装成功');
      loadItems();
      setDetailDialogOpen(false);
    } catch (error: any) {
      console.error('下载失败:', error);
      toast.error(error.message || '下载失败');
    } finally {
      setDownloading(null);
    }
  };

  const openGitHub = () => {
    if (selectedItem?.url?.github) {
      window.open(selectedItem.url.github, '_blank');
    }
  };

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-3xl font-bold tracking-tight">商店</h1>
        <p className="text-muted-foreground">浏览和安装主题、插件</p>
      </div>

      <Tabs value={activeTab} onValueChange={(v) => setActiveTab(v as 'theme' | 'plugin')}>
        <TabsList>
          <TabsTrigger value="theme">主题</TabsTrigger>
          <TabsTrigger value="plugin">插件</TabsTrigger>
        </TabsList>

        <TabsContent value="theme" className="mt-6">
          <ItemGrid
            items={items}
            loading={loading}
            emptyMessage="暂无可用主题"
            showScreenshot={true}
            onDetailClick={handleViewDetail}
            detailButtonText="查看详情"
          />
        </TabsContent>

        <TabsContent value="plugin" className="mt-6">
          <ItemGrid
            items={items}
            loading={loading}
            emptyMessage="暂无可用插件"
            showScreenshot={false}
            onDetailClick={handleViewDetail}
            detailButtonText="查看详情"
          />
        </TabsContent>
      </Tabs>

      {/* 详情对话框 */}
      <Dialog open={detailDialogOpen} onOpenChange={setDetailDialogOpen}>
        <DialogContent className="max-w-4xl max-h-[80vh] overflow-y-auto">
          <DialogHeader>
            <DialogTitle>{selectedItem?.name}</DialogTitle>
          </DialogHeader>
          
          {detailLoading ? (
            <div className="space-y-4">
              <Skeleton className="h-4 w-full" />
              <Skeleton className="h-4 w-full" />
              <Skeleton className="h-4 w-3/4" />
            </div>
          ) : detailData ? (
            <div className="space-y-4">
              <div className="prose prose-sm max-w-none dark:prose-invert">
                <ReactMarkdown>{detailData.readme}</ReactMarkdown>
              </div>
              
              <div className="flex items-center gap-2 text-sm text-muted-foreground">
                <span>远程版本: {detailData.remote_version}</span>
                {detailData.local_version && (
                  <>
                    <span>|</span>
                    <span>本地版本: {detailData.local_version}</span>
                    {detailData.needs_update && (
                      <span className="text-orange-500">(可升级)</span>
                    )}
                  </>
                )}
              </div>
            </div>
          ) : (
            <p className="text-muted-foreground">暂无详细说明</p>
          )}
          
          <DialogFooter>
            <Button variant="outline" onClick={openGitHub} disabled={!selectedItem?.url?.github}>
              <Github className="w-4 h-4 mr-2" />
              查看仓库
            </Button>
            
            <Button 
              onClick={handleDownload} 
              disabled={downloading === selectedItem?.name || !selectedItem?.url?.github}
            >
              {downloading === selectedItem?.name ? (
                <>
                  <Loader2 className="w-4 h-4 mr-2 animate-spin" />
                  {detailData?.needs_update ? '升级中...' : '安装中...'}
                </>
              ) : detailData?.needs_update ? (
                <>
                  <Download className="w-4 h-4 mr-2" />
                  升级主题
                </>
              ) : detailData?.is_installed ? (
                '已是最新'
              ) : (
                <>
                  <Download className="w-4 h-4 mr-2" />
                  下载安装
                </>
              )}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </div>
  );
}
