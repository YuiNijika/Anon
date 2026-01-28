import { useState, useEffect, useRef } from 'react'
import { useApiAdmin } from './useApiAdmin'

interface ConfigData {
    token?: boolean
    captcha?: boolean
    csrfToken?: string
    [key: string]: any
}

let globalConfig: ConfigData | null = null
let configPromise: Promise<ConfigData | null> | null = null

/**
 * 加载配置信息
 */
async function loadConfig(apiAdmin: ReturnType<typeof useApiAdmin>): Promise<ConfigData | null> {
    try {
        // 使用普通 API 的 /get-config 接口
        const res = await apiAdmin.api.get<ConfigData>('/get-config')
        if (res.code === 200 && res.data) {
            globalConfig = res.data
            return globalConfig
        }
    } catch (err) {
        console.error('Config loading failed:', err)
    }
    return null
}

/**
 * 确保配置已加载
 */
export async function ensureConfigLoaded(apiAdmin: ReturnType<typeof useApiAdmin>): Promise<ConfigData | null> {
    if (globalConfig) {
        return globalConfig
    }

    if (configPromise) {
        return configPromise
    }

    configPromise = loadConfig(apiAdmin).finally(() => {
        configPromise = null
    })

    return configPromise
}

export function useCmsConfig() {
    const apiAdmin = useApiAdmin()
    const [loading, setLoading] = useState(() => globalConfig === null)
    const [config, setConfig] = useState<ConfigData | null>(globalConfig)
    const [error, setError] = useState<Error | null>(null)
    const mountedRef = useRef(true)

    useEffect(() => {
        mountedRef.current = true
        return () => {
            mountedRef.current = false
        }
    }, [])

    useEffect(() => {
        if (globalConfig) {
            if (mountedRef.current) {
                setConfig(globalConfig)
                setLoading(false)
            }
            return
        }

        const fetch = async () => {
            try {
                setLoading(true)
                const result = await ensureConfigLoaded(apiAdmin)
                if (mountedRef.current) {
                    setConfig(result)
                    setLoading(false)
                }
            } catch (err) {
                if (mountedRef.current) {
                    setError(err instanceof Error ? err : new Error('加载配置失败'))
                    setLoading(false)
                }
            }
        }

        fetch()
    }, [apiAdmin])

    return {
        config,
        loading,
        error
    }
}
