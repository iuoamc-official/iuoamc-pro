FROM python:3.12-slim
WORKDIR /app
COPY broadcast-nexus/services/playout/requirements.txt .
RUN pip install --no-cache-dir -r requirements.txt
COPY broadcast-nexus/packages /app/packages
COPY broadcast-nexus/services/playout/app /app/app
ENV PYTHONPATH=/app
USER 65532:65532
EXPOSE 8103
CMD ["uvicorn","app.main:app","--host","0.0.0.0","--port","8103"]
