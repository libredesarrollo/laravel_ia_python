"""Local RAG document ingestion pipeline.

Pure-Python, on-premise components. Outbound endpoints are exclusively local
services (PostgreSQL and Ollama on localhost); no external API is contacted.
"""

from __future__ import annotations

__version__ = "0.1.0"
