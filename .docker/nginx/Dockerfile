FROM nginx:alpine

# Copy nginx configuration
COPY ./.docker/nginx/default.conf /etc/nginx/conf.d/default.conf

# Copy application files (needed for serving static files)
COPY ./public /var/www/public

WORKDIR /var/www

EXPOSE 80

CMD ["nginx", "-g", "daemon off;"]
