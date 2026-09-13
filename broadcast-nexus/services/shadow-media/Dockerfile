FROM debian:bookworm-slim
RUN apt-get update && apt-get install -y --no-install-recommends ffmpeg ca-certificates bash fontconfig fonts-noto-core && rm -rf /var/lib/apt/lists/*
WORKDIR /opt/shadow
COPY broadcast-nexus/services/shadow-media/source.sh /opt/shadow/source.sh
COPY broadcast-nexus/services/shadow-media/preview-source.sh /opt/shadow/preview-source.sh
COPY broadcast-nexus/services/shadow-media/encoder.sh /opt/shadow/encoder.sh
COPY broadcast-nexus/services/shadow-media/ticker.txt /opt/shadow/ticker.txt
RUN chmod +x /opt/shadow/source.sh /opt/shadow/preview-source.sh /opt/shadow/encoder.sh
ENTRYPOINT ["/bin/bash"]
