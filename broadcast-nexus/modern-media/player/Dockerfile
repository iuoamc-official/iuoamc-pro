FROM alpine:3.22 AS assets
ARG MEDIAMTX_VERSION=v1.21.0
RUN apk add --no-cache ca-certificates wget \
    && mkdir -p /out \
    && wget -q -O /out/reader.js \
      "https://raw.githubusercontent.com/bluenviron/mediamtx/${MEDIAMTX_VERSION}/internal/servers/webrtc/reader.js"

FROM nginx:1.27-alpine
COPY modern-media/player/nginx.conf /etc/nginx/conf.d/default.conf
COPY modern-media/player/index.html /usr/share/nginx/html/index.html
COPY --from=assets /out/reader.js /usr/share/nginx/html/reader.js
EXPOSE 8080
