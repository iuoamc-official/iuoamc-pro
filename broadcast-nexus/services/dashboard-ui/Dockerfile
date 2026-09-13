FROM nginx:1.27-alpine
COPY broadcast-nexus/services/dashboard-ui/ /usr/share/nginx/html/
COPY public/assets/brand/master-v1/iuoamc-tv-seal-v1.webp /usr/share/nginx/html/branding/iuoamc-tv-logo.webp
COPY broadcast-nexus/services/dashboard-ui/default.conf /etc/nginx/conf.d/default.conf
EXPOSE 80
