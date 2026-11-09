"""Typed configuration shared by the ingestion pipeline.

Values come from environment variables so Laravel can inject them per-process
without exposing anything to the filesystem or network.
"""

from __future__ import annotations

import os
from dataclasses import dataclass
from typing import Final

DEFAULT_OLLAMA_BASE_URL: Final[str] = "http://127.0.0.1:11434"
DEFAULT_EMBED_MODEL: Final[str] = "nomic-embed-text"
DEFAULT_EMBEDDING_DIMENSIONS: Final[int] = 768
DEFAULT_EMBED_BATCH_SIZE: Final[int] = 32
DEFAULT_CHUNK_SIZE: Final[int] = 1000
DEFAULT_CHUNK_OVERLAP: Final[int] = 200


@dataclass(frozen=True, slots=True)
class Settings:
    """Runtime settings resolved from the environment."""

    host: str
    port: int
    user: str
    password: str
    database: str
    ollama_base_url: str
    embedding_model: str
    embedding_dimensions: int
    embedding_batch_size: int
    chunk_size: int
    chunk_overlap: int

    @classmethod
    def from_environment(cls) -> "Settings":
        """Build settings from DB_* / OLLAMA_* / RAG_* environment variables."""
        return cls(
            host=os.environ.get("RAG_DB_HOST", "127.0.0.1"),
            port=int(os.environ.get("RAG_DB_PORT", "5432")),
            user=os.environ.get("RAG_DB_USERNAME", "postgres"),
            password=os.environ.get("RAG_DB_PASSWORD", ""),
            database=os.environ.get("RAG_DB_DATABASE", "rag_local"),
            ollama_base_url=(
                os.environ.get("OLLAMA_BASE_URL", DEFAULT_OLLAMA_BASE_URL).rstrip("/")
            ),
            embedding_model=os.environ.get("OLLAMA_EMBED_MODEL", DEFAULT_EMBED_MODEL),
            embedding_dimensions=(
                int(os.environ.get("RAG_EMBEDDING_DIMENSIONS", str(DEFAULT_EMBEDDING_DIMENSIONS)))
            ),
            embedding_batch_size=(
                int(os.environ.get("OLLAMA_EMBED_BATCH_SIZE", str(DEFAULT_EMBED_BATCH_SIZE)))
            ),
            chunk_size=int(os.environ.get("RAG_CHUNK_SIZE", str(DEFAULT_CHUNK_SIZE))),
            chunk_overlap=int(os.environ.get("RAG_CHUNK_OVERLAP", str(DEFAULT_CHUNK_OVERLAP))),
        )


__all__ = [
    "DEFAULT_EMBED_BATCH_SIZE",
    "DEFAULT_EMBEDDING_DIMENSIONS",
    "DEFAULT_EMBED_MODEL",
    "DEFAULT_OLLAMA_BASE_URL",
    "Settings",
]
