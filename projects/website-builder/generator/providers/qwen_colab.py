"""Authenticated, fail-closed adapter for a user-hosted Colab Qwen endpoint.

This module never starts a model or tunnel on the website-builder host. The API
contract is documented in docs/QWEN_COLAB.md and is not an existing endpoint.
"""

from __future__ import annotations

import json
import os
import re
import secrets
import sqlite3
import stat
import time
import urllib.error
import urllib.parse
import urllib.request
from pathlib import Path
from typing import Callable

MODEL_ID = "Qwen/Qwen2.5-Coder-7B-Instruct"
ALLOWED_FILES = {"index.html", "css/style.css", "js/main.js"}
MAX_FILE_BYTES = 256 * 1024
MAX_PROMPT_BYTES = 64 * 1024
_JOB_ID = re.compile(r"^[a-f0-9]{32}$")


class _RejectRedirects(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, req, fp, code, msg, headers, newurl):
        return None


def _open_without_redirects(request, timeout):
    opener = urllib.request.build_opener(_RejectRedirects())
    return opener.open(request, timeout=timeout)


class ProviderError(RuntimeError):
    """Sanitized provider error suitable for local status output."""


class ProviderDisabled(ProviderError):
    pass


class ProviderUnavailable(ProviderError):
    pass


class InvalidProviderResponse(ProviderError):
    pass


class QwenConfig:
    def __init__(self, endpoint: str | None, token_file: str | None, job_db: str | None):
        self.endpoint = self._validate_endpoint(endpoint) if endpoint else None
        self.token_file = Path(token_file).expanduser() if token_file else None
        self.job_db = Path(job_db).expanduser() if job_db else Path.home() / ".local/state/website-builder/qwen-jobs.sqlite3"

    @staticmethod
    def _validate_endpoint(value: str) -> str:
        parsed = urllib.parse.urlsplit(value)
        if parsed.scheme != "https" or not parsed.hostname or parsed.username or parsed.password:
            raise ProviderDisabled("Colab endpoint must be an HTTPS URL without embedded credentials")
        if parsed.query or parsed.fragment:
            raise ProviderDisabled("Colab endpoint URL must not include query or fragment data")
        return value.rstrip("/")

    @classmethod
    def from_environment(cls) -> "QwenConfig":
        return cls(
            os.environ.get("WEBSITE_BUILDER_QWEN_COLAB_URL"),
            os.environ.get("WEBSITE_BUILDER_QWEN_COLAB_TOKEN_FILE"),
            os.environ.get("WEBSITE_BUILDER_QWEN_JOB_DB"),
        )

    def read_token(self) -> str:
        if not self.token_file:
            raise ProviderDisabled("Colab authentication token file is not configured")
        try:
            info = self.token_file.lstat()
            if (not stat.S_ISREG(info.st_mode) or info.st_uid != os.geteuid()
                    or stat.S_IMODE(info.st_mode) & 0o077):
                raise ProviderDisabled("Colab token file must be a regular file with owner-only permissions")
            token = self.token_file.read_text(encoding="utf-8").strip()
        except FileNotFoundError as exc:
            raise ProviderDisabled("Colab authentication token file is unavailable") from exc
        except OSError as exc:
            raise ProviderDisabled("Colab authentication token file cannot be read") from exc
        if not token or "\n" in token or "\r" in token:
            raise ProviderDisabled("Colab authentication token file is empty or malformed")
        return token


class JobQueue:
    """Small persistent queue; no background worker or public listener is started."""

    def __init__(self, path: Path):
        self.path = path
        self.path.parent.mkdir(parents=True, exist_ok=True, mode=0o700)
        parent_info = self.path.parent.lstat()
        if not stat.S_ISDIR(parent_info.st_mode) or parent_info.st_uid != os.geteuid():
            raise ProviderDisabled("job database directory must be an owner-controlled directory")
        os.chmod(self.path.parent, 0o700)
        self._initialize()

    def _connect(self):
        db = sqlite3.connect(self.path, timeout=5)
        db.execute("PRAGMA journal_mode=DELETE")
        db.execute("PRAGMA synchronous=FULL")
        return db

    def _initialize(self):
        if self.path.is_symlink():
            raise ProviderDisabled("job database path must not be a symlink")
        if self.path.exists():
            info = self.path.lstat()
            if not stat.S_ISREG(info.st_mode) or info.st_uid != os.geteuid():
                raise ProviderDisabled("job database must be a regular file owned by the builder user")
        with self._connect() as db:
            db.execute("""CREATE TABLE IF NOT EXISTS jobs (
                id TEXT PRIMARY KEY, status TEXT NOT NULL, prompt TEXT NOT NULL,
                theme TEXT NOT NULL, created_at REAL NOT NULL, updated_at REAL NOT NULL,
                attempts INTEGER NOT NULL DEFAULT 0, retry_after REAL, error_code TEXT
            )""")
        os.chmod(self.path, 0o600)

    def enqueue(self, prompt: str, theme: str) -> str:
        if len(prompt.encode("utf-8")) > MAX_PROMPT_BYTES:
            raise ValueError("prompt exceeds the configured size limit")
        job_id = secrets.token_hex(16)
        now = time.time()
        with self._connect() as db:
            db.execute("INSERT INTO jobs (id,status,prompt,theme,created_at,updated_at) VALUES(?,?,?,?,?,?)",
                       (job_id, "queued", prompt, theme, now, now))
        return job_id

    def get(self, job_id: str) -> dict:
        if not _JOB_ID.fullmatch(job_id):
            raise ValueError("invalid job id")
        with self._connect() as db:
            row = db.execute("SELECT status,attempts,retry_after,error_code FROM jobs WHERE id=?", (job_id,)).fetchone()
        if row is None:
            raise KeyError("job not found")
        return dict(zip(("status", "attempts", "retry_after", "error_code"), row))

    def request_data(self, job_id: str) -> tuple[str, str]:
        with self._connect() as db:
            row = db.execute("SELECT prompt,theme FROM jobs WHERE id=?", (job_id,)).fetchone()
        if row is None:
            raise KeyError("job not found")
        return row[0], row[1]

    def update(self, job_id: str, status: str, error_code: str | None = None, retry_after: float | None = None):
        if status not in {"queued", "running", "completed", "failed"}:
            raise ValueError("invalid job status")
        with self._connect() as db:
            db.execute("UPDATE jobs SET status=?,updated_at=?,attempts=attempts+1,error_code=?,retry_after=? WHERE id=?",
                       (status, time.time(), error_code, retry_after, job_id))


class QwenColabAdapter:
    def __init__(self, config: QwenConfig, opener: Callable = _open_without_redirects,
                 health_timeout: float = 5.0, generation_timeout: float = 180.0):
        self.config = config
        self.opener = opener
        self.health_timeout = health_timeout
        self.generation_timeout = generation_timeout
        self.queue = JobQueue(config.job_db)

    @classmethod
    def from_environment(cls) -> "QwenColabAdapter":
        return cls(QwenConfig.from_environment())

    def job_status(self, job_id: str) -> str:
        return self.queue.get(job_id)["status"]

    def _request(self, path: str, payload: dict | None, timeout: float):
        if not self.config.endpoint:
            raise ProviderDisabled("authenticated Colab endpoint is not configured")
        token = self.config.read_token()
        data = json.dumps(payload).encode("utf-8") if payload is not None else None
        request = urllib.request.Request(
            f"{self.config.endpoint}{path}", data=data,
            headers={"Authorization": f"Bearer {token}", "Accept": "application/json",
                     **({"Content-Type": "application/json"} if data is not None else {})},
            method="POST" if data is not None else "GET",
        )
        try:
            with self.opener(request, timeout=timeout) as response:
                if response.status != 200:
                    raise ProviderUnavailable("Colab endpoint returned a non-success status")
                final_url = urllib.parse.urlsplit(response.geturl())
                original_url = urllib.parse.urlsplit(request.full_url)
                if (final_url.scheme != "https" or final_url.hostname != original_url.hostname
                        or final_url.port != original_url.port):
                    raise ProviderUnavailable("Colab endpoint redirected outside its configured HTTPS host")
                raw = response.read(MAX_FILE_BYTES * 4 + 1)
        except (urllib.error.URLError, TimeoutError, OSError) as exc:
            raise ProviderUnavailable("Colab endpoint is unreachable or timed out") from exc
        if len(raw) > MAX_FILE_BYTES * 4:
            raise InvalidProviderResponse("Colab response exceeded the size limit")
        try:
            return json.loads(raw.decode("utf-8"))
        except (UnicodeDecodeError, json.JSONDecodeError) as exc:
            raise InvalidProviderResponse("Colab endpoint returned invalid JSON") from exc

    def health_check(self) -> dict:
        result = self._request("/health", None, self.health_timeout)
        if not isinstance(result, dict) or result.get("status") != "ok":
            raise ProviderUnavailable("Colab model health check did not pass")
        if result.get("model") != MODEL_ID:
            raise ProviderUnavailable("Colab health check reports an unexpected model")
        if str(result.get("quantization", "")).lower().replace("-", "") not in {"4bit", "nf4"}:
            raise ProviderUnavailable("Colab health check does not confirm 4-bit quantization")
        if "t4" not in str(result.get("device", "")).lower():
            raise ProviderUnavailable("Colab health check does not confirm Tesla T4 inference")
        return {"status": "ok", "model": MODEL_ID, "quantization": "4-bit", "device": "Tesla T4"}

    @staticmethod
    def _validate_files(result: object) -> dict[str, str]:
        if not isinstance(result, dict) or set(result) != {"files"} or not isinstance(result["files"], dict):
            raise InvalidProviderResponse("generation response must contain a files object")
        files = result["files"]
        if not files or not set(files).issubset(ALLOWED_FILES):
            raise InvalidProviderResponse("generation response contains an unsupported project path")
        safe = {}
        for name, content in files.items():
            if not isinstance(name, str) or not isinstance(content, str):
                raise InvalidProviderResponse("generated project files must be UTF-8 text")
            if len(content.encode("utf-8")) > MAX_FILE_BYTES:
                raise InvalidProviderResponse("a generated project file exceeded the size limit")
            safe[name] = content
        return safe

    def generate_files(self, prompt: str, theme: str) -> tuple[str, dict[str, str] | None]:
        job_id = self.queue.enqueue(prompt, theme)
        if not self.config.endpoint or not self.config.token_file:
            return job_id, None
        try:
            self.health_check()
            self.queue.update(job_id, "running")
            stored_prompt, stored_theme = self.queue.request_data(job_id)
            result = self._request("/v1/generate", {
                "model": MODEL_ID, "prompt": stored_prompt, "theme": stored_theme,
                "max_file_bytes": MAX_FILE_BYTES, "allowed_files": sorted(ALLOWED_FILES),
            }, self.generation_timeout)
            files = self._validate_files(result)
            self.queue.update(job_id, "completed")
            return job_id, files
        except ProviderDisabled:
            self.queue.update(job_id, "queued", "configuration_missing", time.time() + 60)
            return job_id, None
        except ProviderUnavailable:
            self.queue.update(job_id, "queued", "provider_unavailable", time.time() + 60)
            return job_id, None
        except InvalidProviderResponse:
            self.queue.update(job_id, "failed", "invalid_provider_response")
            raise
