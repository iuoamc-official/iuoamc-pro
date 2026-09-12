FROM python:3.12-slim
WORKDIR /app
COPY broadcast-nexus/services/shadow-program-switcher/requirements.txt .
RUN pip install --no-cache-dir -r requirements.txt
COPY broadcast-nexus/services/shadow-program-switcher/app /app/app
EXPOSE 8119
CMD ["uvicorn","app.main:app","--host","0.0.0.0","--port","8119"]
