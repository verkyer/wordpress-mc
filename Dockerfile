# 基于WordPress官方镜像
FROM wordpress:latest

# 安装系统依赖
# 移除 shadow 包，因为 usermod/groupmod 几乎肯定已包含在基础镜像中
RUN apt-get update && apt-get install -y \
    libmemcached-dev \
    zlib1g-dev \
    libssl-dev \
    unzip \
    && rm -rf /var/lib/apt/lists/*
    # 注意：这里已经移除了 shadow

# 安装PHP扩展
RUN docker-php-ext-enable opcache
RUN pecl install memcached && docker-php-ext-enable memcached

# 复制配置文件 (假设这些文件在宿主机上存在)
COPY php.ini /usr/local/etc/php/
COPY opcache.ini /usr/local/etc/php/conf.d/
COPY memcached.ini /usr/local/etc/php/conf.d/

# 创建目录结构并复制WordPress object-cache.php文件到源目录
RUN mkdir -p /usr/src/wordpress/wp-content
COPY wp-content/object-cache.php /usr/src/wordpress/wp-content/object-cache.php

# 创建plugins目录并下载WPJAM-Basic插件到源目录
RUN mkdir -p /usr/src/wordpress/wp-content/plugins && \
    cd /tmp && \
    curl -L -o wpjam-basic.zip https://downloads.wordpress.org/plugin/wpjam-basic.zip && \
    unzip wpjam-basic.zip -d /usr/src/wordpress/wp-content/plugins/ && \
    rm wpjam-basic.zip

# 设置源目录权限
RUN chown -R www-data:www-data /usr/src/wordpress

# 备份原始的WordPress入口点脚本
RUN mv /usr/local/bin/docker-entrypoint.sh /usr/local/bin/wordpress-entrypoint.sh

# 复制自定义启动脚本
COPY docker-entrypoint.sh /usr/local/bin/
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

# 设置自定义入口点
ENTRYPOINT ["/usr/local/bin/docker-entrypoint.sh"]
CMD ["apache2-foreground"]

# 健康检查
HEALTHCHECK --interval=30s --timeout=3s --start-period=5s --retries=3 \
    CMD curl -f http://localhost/ || exit 1
