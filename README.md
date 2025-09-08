# WordPress-MC Docker项目

一个基于 WordPress 官方的定制化镜像，集成了 MySQL 数据库和 OPcache + Memcached 缓存，让你的 WordPress 起飞！并支持灵活的环境变量配置。

## 功能特性

- 🚀 **开箱即用**：一键启动完整的 WordPress 开发环境
- ⚡ **缓存优化**：集成 OPcache + Memcached 缓存服务，提升性能
- 🔧 **灵活配置**：支持环境变量动态配置 PHP 参数
- 📁 **数据持久化**：WordPress 文件和数据库数据本地持久化
- 🔒 **安全隔离**：使用 Docker 网络隔离服务
- 🎈 **插件集成**：添加 WPJAM-Basic 插件（一键式全站优化）

## 快速开始

1. 克隆或下载项目文件
2. 根据需要选择合适的 docker-compose 配置文件
3. 运行 `docker-compose up -d` 启动服务
4. 访问 `http://localhost:8080` 开始使用 WordPress

### Docker Compose 示例

```
services:
  wordpress:
    image: verky/wordpress-mc:latest
    ports:
      - "8080:80"
    environment:
      WORDPRESS_DB_HOST: db
      WORDPRESS_DB_USER: wordpress #数据库用户
      WORDPRESS_DB_PASSWORD: wordpress #数据库密码（自己修改）
      WORDPRESS_DB_NAME: wordpress #数据库名
    volumes:
      - ./wordpress:/var/www/html
    depends_on:
      - db
      - memcached
    networks:
      - wordpress_network

  db:
    image: mysql:5.7.44
    environment:
      MYSQL_DATABASE: wordpress #数据库名
      MYSQL_USER: wordpress #数据库用户
      MYSQL_PASSWORD: wordpress #数据库密码（自己修改）
      MYSQL_ROOT_PASSWORD: rootpassword #数据库root密码
    volumes:
      - ./db:/var/lib/mysql
    networks:
      - wordpress_network

  memcached:
    image: memcached:alpine
    command: memcached -m 64
    networks:
      - wordpress_network

networks:
  wordpress_network:
    driver: bridge
```

### 数据库版本选择

- **2GB 内存以下**：推荐使用 MySQL 5.7.44
- **2GB 内存以上**：推荐使用 MySQL 8.0+

## 环境变量、映射

#### WP 数据库连接
- `WORDPRESS_DB_HOST`：数据库主机地址，如果已有数据库，不用编排的请修改！
- `WORDPRESS_DB_USER`：数据库用户名
- `WORDPRESS_DB_PASSWORD`：数据库密码
- `WORDPRESS_DB_NAME`：数据库名称

#### PHP 性能配置（上传文件大小修改）
- `UPLOAD_MAX_FILESIZE`：单个文件上传的最大大小限制（默认：64M）
- `POST_MAX_SIZE`：整个 POST 请求的最大大小限制（默认：64M）
- `MEMORY_LIMIT`：PHP 脚本运行时的最大内存限制（默认：256M）
- `PHP_MAX_EXECUTION_TIME`：脚本最大执行时间（秒）（默认：600）

#### 数据持久化
- `./wordpress`：WordPress 文件目录
- `./db`：MySQL 数据库文件

### Docker Compose 示例（外部数据库）
适用于已有外部数据库的场景
```
services:
  wordpress:
    image: verky/wordpress-mc:latest
    ports:
      - "8080:80"
    environment:
      WORDPRESS_DB_HOST: 127.0.0.1:3306     # 数据库地址
      WORDPRESS_DB_USER: wordpress          # 数据库用户名
      WORDPRESS_DB_PASSWORD: wordpress      # 数据库密码
      WORDPRESS_DB_NAME: wordpress          # 数据库名称
    volumes:
      - ./wordpress:/var/www/html
    depends_on:
      - memcached
    networks:
      - wordpress_network

  memcached:
    image: memcached:alpine
    command: memcached -m 64  # 缓存内存大小，可根据需要调整
    networks:
      - wordpress_network

networks:
  wordpress_network:
    driver: bridge
```

## 开发说明

### 目录结构

```
wordpress-mc/
├── docker-compose.yml              # Docker Compose 示例
├── docker-compose.external-db.yml  # Docker Compose 示例（外部、已有数据库）
├── Dockerfile                       # WordPress 镜像构建文件
├── docker-entrypoint.sh            # 容器启动脚本
├── wp-content/                      # WordPress 插件和主题
│   └── object-cache.php            # Memcached 缓存配置
├── php.ini                         # PHP 配置文件
├── opcache.ini                     # OPcache 配置
├── memcached.ini                   # Memcached 扩展配置
└── README.md                       # 项目说明文档
```

### 自定义配置

- **PHP 配置**：修改 `php.ini` 文件
- **缓存配置**：修改 `wp-content/object-cache.php`
- **数据库配置**：修改 `docker-compose.yml` 中的环境变量

## 许可证

MIT License

## 贡献

欢迎提交 Issue 和 Pull Request！

## 支持

如果这个项目对您有帮助，请给个 ⭐️ Star！