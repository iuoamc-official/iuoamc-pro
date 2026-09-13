FROM python:3.12-slim
WORKDIR /app
COPY broadcast-nexus/services/dashboard-api/requirements.txt ./requirements.txt
RUN pip install --no-cache-dir -r requirements.txt
COPY broadcast-nexus/services/dashboard-api/app /app/app
USER 65532:65532
EXPOSE 8121
CMD ["uvicorn","app.main:app","--host","0.0.0.0","--port","8121"]
