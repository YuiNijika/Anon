import { useApiAdmin } from './useApiAdmin';

export interface StoreItem {
  name: string;
  version: string;
  description: string;
  author: string;
  screenshot?: string;
  download_url: string;
  updated_at: string;
  type: 'theme' | 'plugin';
  url?: {
    github?: string;
    gitee?: string;
  };
}

export interface StoreResponse {
  items: StoreItem[];
  total: number;
  type: 'theme' | 'plugin';
}

/**
 * 在线商店 Hook
 */
export function useStore() {
  const api = useApiAdmin();

  /**
   * 获取项目列表
   * @param type 类型
   */
  const getItems = async (type: 'theme' | 'plugin' = 'theme') => {
    try {
      const response = await api.admin.get('/store/list', { type });
      return response.data as StoreResponse;
    } catch (error) {
      console.error('获取列表失败:', error);
      throw error;
    }
  };

  /**
   * 下载项目
   * @param itemName 项目名称
   * @param type 类型
   */
  const downloadItem = async (itemName: string, type: 'theme' | 'plugin' = 'theme') => {
    try {
      const response = await api.admin.post('/store/download', {
        name: itemName,
        type,
      });
      return response.data;
    } catch (error) {
      console.error('下载项目失败:', error);
      throw error;
    }
  };

  /**
   * 获取项目详情
   * @param name 项目名称
   * @param type 类型
   */
  const getDetail = async (name: string, type: 'theme' | 'plugin' = 'theme') => {
    try {
      const response = await api.admin.post('/store/detail', {
        name,
        type,
      });
      return response.data;
    } catch (error) {
      console.error('获取详情失败:', error);
      throw error;
    }
  };

  return {
    getItems,
    downloadItem,
    getDetail,
  };
}
