FROM python:3.12-slim
WORKDIR /app
COPY broadcast-nexus/services/shadow-noc-bridge/requirements.txt .
RUN pip install --no-cache-dir -r requirements.txt
COPY broadcast-nexus/services/shadow-noc-bridge/app /app/app
CMD ["python","-m","app.main"]
