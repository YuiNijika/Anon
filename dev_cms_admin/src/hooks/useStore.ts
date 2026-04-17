import { useApiAdmin } from './useApiAdmin';

export interface StoreItem {
  name: string;
  author: string;
  description: string;
  screenshot?: string;
  type: 'theme' | 'plugin';
  category?: string;
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
   * 获取 GitHub 镜像配置
   */
  const getMirrorConfig = async () => {
    try {
      const response = await api.admin.get('/settings/basic');
      if (response.data) {
        return {
          github_mirror: response.data.github_mirror || '',
          github_raw_mirror: response.data.github_raw_mirror || '',
        };
      }
    } catch (error) {
      console.warn('获取镜像配置失败:', error);
    }
    return { github_mirror: '', github_raw_mirror: '' };
  };

  /**
   * 应用 GitHub 镜像
   */
  const applyGithubMirror = (url: string, mirror: string, rawMirror: string): string => {
    // 优先使用 raw 镜像
    if (url.includes('raw.githubusercontent.com') && rawMirror) {
      return url.replace('https://raw.githubusercontent.com', rawMirror.replace(/\/$/, ''));
    }
    
    // 使用通用镜像
    if (mirror) {
      return url.replace('https://github.com', mirror.replace(/\/$/, ''));
    }
    
    return url;
  };

  /**
   * 获取项目列表
   */
  const getItems = async (type: 'theme' | 'plugin' = 'theme') => {
    try {
      // 获取镜像配置
      const { github_mirror, github_raw_mirror } = await getMirrorConfig();
      
      // 直接请求 GitHub raw JSON
      let repoUrl = type === 'plugin' 
        ? 'https://raw.githubusercontent.com/YuiNijika/AnonCMS-Plugin-API/main/list.json'
        : 'https://raw.githubusercontent.com/YuiNijika/AnonCMS-Theme-API/main/list.json';
      
      // 应用镜像
      repoUrl = applyGithubMirror(repoUrl, github_mirror, github_raw_mirror);
      
      const response = await fetch(repoUrl);
      if (!response.ok) {
        throw new Error('获取列表失败');
      }
      
      const indexData = await response.json();
      
      if (!indexData.data || !indexData.repository) {
        throw new Error('数据格式错误');
      }

      // 转换 repository URL 为 raw URL
      let baseUrl = indexData.repository.replace(
        'https://github.com/',
        'https://raw.githubusercontent.com/'
      ).replace('/blob/main/', '/main/').replace(/\/$/, '');
      
      // 应用镜像
      baseUrl = applyGithubMirror(baseUrl, github_mirror, github_raw_mirror);

      // 遍历所有类型合并数据
      const allItems: StoreItem[] = [];
      
      for (const [typeName, jsonFile] of Object.entries(indexData.data)) {
        try {
          const typeUrl = `${baseUrl}/${jsonFile}`;
          const typeResponse = await fetch(typeUrl);
          
          if (typeResponse.ok) {
            const typeData = await typeResponse.json();
            
            if (typeData.data && Array.isArray(typeData.data)) {
              // 添加类型和分类字段，并处理 screenshot
              const itemsWithType = typeData.data.map((item: any) => {
                // 处理 screenshot：基于主题自己的仓库URL拼接
                let processedScreenshot = item.screenshot;
                if (item.screenshot) {
                  // 判断是否是完整 URL
                  if (item.screenshot.startsWith('http://') || item.screenshot.startsWith('https://')) {
                    // 已经是完整 URL，只应用一次镜像
                    processedScreenshot = applyGithubMirror(item.screenshot, github_mirror, github_raw_mirror);
                  } else {
                    // 是文件名，需要基于主题的仓库URL拼接
                    // 优先使用 github，其次使用 gitee
                    const repoUrl = item.url?.github || item.url?.gitee;
                    
                    if (repoUrl) {
                      // 将 GitHub/Gitee 仓库 URL 转换为 raw URL
                      let rawBaseUrl = repoUrl
                        .replace('https://github.com/', 'https://raw.githubusercontent.com/')
                        .replace('https://gitee.com/', 'https://gitee.com/')
                        .replace(/\/$/, '');
                      
                      // Gitee 的 raw URL 格式不同
                      if (repoUrl.includes('gitee.com')) {
                        rawBaseUrl = `${repoUrl}/raw/master`;
                      } else {
                        // GitHub: https://github.com/user/repo -> https://raw.githubusercontent.com/user/repo/main
                        rawBaseUrl = rawBaseUrl.replace('/blob/main', '/main').replace('/blob/master', '/master');
                        if (!rawBaseUrl.includes('/main') && !rawBaseUrl.includes('/master')) {
                          rawBaseUrl += '/main';
                        }
                      }
                      
                      // 拼接 screenshot
                      processedScreenshot = `${rawBaseUrl}/${item.screenshot}`;
                      
                      // 应用镜像
                      processedScreenshot = applyGithubMirror(processedScreenshot, github_mirror, github_raw_mirror);
                    }
                    // 如果没有仓库URL，保持原样
                  }
                }
                
                return {
                  ...item,
                  screenshot: processedScreenshot,
                  type,
                  category: typeName,
                };
              });
              
              allItems.push(...itemsWithType);
            }
          }
        } catch (error) {
          console.warn(`获取 ${typeName} 失败:`, error);
        }
      }

      return {
        items: allItems,
        total: allItems.length,
        type,
      } as StoreResponse;
    } catch (error) {
      console.error('获取列表失败:', error);
      throw error;
    }
  };

  /**
   * 下载项目
   */
  const downloadItem = async (itemName: string, type: 'theme' | 'plugin' = 'theme', downloadUrl: string) => {
    try {
      const response = await api.admin.post('/store/download', {
        name: itemName,
        type,
        url: downloadUrl,
      });
      return response.data;
    } catch (error) {
      console.error('下载项目失败:', error);
      throw error;
    }
  };

  /**
   * 获取项目详情（README）
   */
  const getDetail = async (item: StoreItem) => {
    try {
      if (!item.url?.github) {
        throw new Error('缺少仓库地址');
      }

      // 获取镜像配置
      const { github_mirror, github_raw_mirror } = await getMirrorConfig();

      // 从 GitHub URL 构建 raw URL
      let rawBaseUrl = item.url.github.replace(
        'https://github.com/',
        'https://raw.githubusercontent.com/'
      ).replace(/\/$/, '');
      
      // 应用镜像
      rawBaseUrl = applyGithubMirror(rawBaseUrl, github_mirror, github_raw_mirror);

      // 获取 README.md
      const readmeUrl = `${rawBaseUrl}/main/README.md`;
      const readmeResponse = await fetch(readmeUrl);
      
      if (!readmeResponse.ok) {
        throw new Error('获取 README 失败');
      }
      
      const readmeContent = await readmeResponse.text();

      // 获取 package.json 版本信息
      let remoteVersion = '1.0.0';
      try {
        const packageUrl = `${rawBaseUrl}/main/package.json`;
        const packageResponse = await fetch(packageUrl);
        
        if (packageResponse.ok) {
          const packageData = await packageResponse.json();
          if (packageData.version) {
            remoteVersion = packageData.version;
          }
        }
      } catch (error) {
        console.warn('获取版本信息失败:', error);
      }

      // 检查本地版本
      let localVersion: string | null = null;
      try {
        const checkUrl = `/api/admin/store/check-version?name=${encodeURIComponent(item.name)}&type=${item.type}`;
        const checkResponse = await fetch(checkUrl);
        
        if (checkResponse.ok) {
          const checkData = await checkResponse.json();
          if (checkData.data && checkData.data.version) {
            localVersion = checkData.data.version;
          }
        }
      } catch (error) {
        console.warn('检查本地版本失败:', error);
      }

      const needsUpdate = localVersion !== null && compareVersions(localVersion, remoteVersion) < 0;

      return {
        readme: readmeContent,
        remote_version: remoteVersion,
        local_version: localVersion,
        is_installed: localVersion !== null,
        needs_update: needsUpdate,
      };
    } catch (error) {
      console.error('获取详情失败:', error);
      throw error;
    }
  };

  /**
   * 比较版本号
   */
  function compareVersions(v1: string, v2: string): number {
    const parts1 = v1.split('.').map(Number);
    const parts2 = v2.split('.').map(Number);
    const len = Math.max(parts1.length, parts2.length);
    
    for (let i = 0; i < len; i++) {
      const n1 = parts1[i] || 0;
      const n2 = parts2[i] || 0;
      
      if (n1 > n2) return 1;
      if (n1 < n2) return -1;
    }
    
    return 0;
  }

  return {
    getItems,
    downloadItem,
    getDetail,
  };
}
