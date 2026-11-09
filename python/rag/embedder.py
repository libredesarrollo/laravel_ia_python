"""Ollama embedding client (local API only)."""

from __future__ import annotations

from dataclasses import dataclass
from typing import Any, Final, Protocol
from urllib import request as url_request
from urllib.error import HTTPError, URLError

import json
import socket

from rag.errors import EmbedError

_TIMEOUT_SECONDS: Final[float] = 120.0
_CONNECTION_TIMEOUT_SECONDS: Final[float] = 10.0


@dataclass(frozen=True, slots=True)
class EmbeddingResult:
    embeddings: list[list[float]]
    total_tokens: int
    batches: int


class Transport(Protocol):
    """Minimal HTTP transport seam, testable without sockets."""

    def post(self, path: str, body: dict[str, Any]) -> dict[str, Any]:
        """POST JSON to Ollama and decode a JSON response."""
        ...


class OllamaTransport:
    def __init__(self, base_url: str) -> None:
        self.base_url = base_url.rstrip("/")

    def post(self, path: str, body: dict[str, Any]) -> dict[str, Any]:
        url = f"{self.base_url}{path}"
        request = url_request.Request(
            url,
            data=json.dumps(body).encode("utf-8"),
            headers={"Content-Type": "application/json"},
            method="POST",
        )
        try:
            with url_request.urlopen(
                request,
                timeout=_TIMEOUT_SECONDS,
            ) as response:  # type: ignore[arg-type]
                return json.loads(response.read().decode("utf-8"))
        except (HTTPError, URLError, socket.timeout, TimeoutError) as error:
            message = f"Ollama connection failure ({url}): {error.__class__.__name__}: {error}"
            raise EmbedError(message) from error


class OllamaEmbedder:
    """Performs the local embedding of chunks via /api/embed."""

    def __init__(
        self,
        settings: Any,
        transport: Transport | None = None,
    ) -> None:
        self.settings = settings
        self.transport = transport or OllamaTransport(settings.ollama_base_url)

    def embed(self, texts: list[str]) -> EmbeddingResult:
        if not texts:
            return EmbeddingResult(embeddings=[], total_tokens=0, batches=0)

        batches: list[list[float]] = []
        total_tokens = 0
        batch_size = max(1, self.settings.embedding_batch_size)
        batch_count = 0
        text_index = 0

        while text_index < len(texts):
            window = texts[text_index : text_index + batch_size]
            embeddings = self._embed_window(window)
            for embedding in embeddings:
                self._conform(embedding)
                batches.append(embedding)
            total_tokens += sum(len(t.split()) for t in window)
            batch_count += 1
            text_index += batch_size
        return EmbeddingResult(
            embeddings=batches,
            total_tokens=total_tokens,
            batches=batch_count,
        )

    def embed_one(self, text: str) -> list[float]:
        """Embed a single string (used by retrieval to embed the query)."""
        return self._embed_window([text])[0]

    def _embed_window(self, window: list[str]) -> list[list[float]]:
        response = self.transport.post(
            "/api/embed",
            {"input": window, "model": self.settings.embedding_model},
        )
        embeddings = response.get("embeddings")
        if not isinstance(embeddings, list):
            message = "Ollama /api/embed returned no embeddings payload"
            raise EmbedError(message)
        for embedding in embeddings:
            if not isinstance(embedding, list):
                message = "Ollama /api/embed returned malformed embeddings"
                raise EmbedError(message)
        return embeddings

    def _conform(self, embedding: list[float]) -> None:
        expected = self.settings.embedding_dimensions
        if len(embedding) != expected:
            message = (
                f"Embedding dimension mismatch: Ollama returned {len(embedding)} "
                f"components, database expects {expected} ({self.settings.embedding_model})."
            )
            raise EmbedError(message)


__all__ = ["EmbeddingResult", "OllamaEmbedder", "OllamaTransport", "Transport"]
