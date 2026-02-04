# Debug 资源构建工具

将 SCSS 和 TypeScript 源码编译为生产环境的 CSS 和 JS 文件。

## 安装依赖

```bash
cd server
npm install
# 或
pnpm install
```

## 构建

```bash
# 构建 CSS 和 JS
npm run build:debug

# 仅构建 CSS
npm run build:debug:css

# 仅构建 JS
npm run build:debug:js
```

## 监听模式

```bash
# 同时监听 CSS 和 JS 变化
npm run watch:debug

# 仅监听 CSS
npm run watch:debug:css

# 仅监听 JS
npm run watch:debug:js
```

## 输出文件

构建后的文件会输出到：
- `../core/Static/debug.css` - 编译后的 CSS
- `../core/Static/debug.js` - 编译后的 JS

## 源码文件

- `scss/debug.scss` - SCSS 源码
- `typescript/debug.ts` - TypeScript 源码

