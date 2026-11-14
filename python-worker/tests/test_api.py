from dataclasses import replace
from fastapi.testclient import TestClient
import app.main as main
from app.config import settings

client = TestClient(main.app)

def test_health_is_public_and_does_not_expose_secrets():
    response = client.get("/health")
    assert response.status_code == 200
    body = response.json()
    assert body["status"] == "ok"
    assert "token" not in body
    assert "password" not in body

def test_process_requires_bearer_token(monkeypatch):
    monkeypatch.setattr(main, "settings", replace(settings, token="unit-test-secret"))
    response = client.post("/internal/process", json={"document_id": 1})
    assert response.status_code == 401
    response = client.post("/internal/process", json={"document_id": 1}, headers={"Authorization": "Bearer wrong"})
    assert response.status_code == 401
