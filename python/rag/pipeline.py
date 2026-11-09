"""Pipeline orchestration: read -> chunk -> embed -> insert -> notify status."""

from __future__ import annotations

import time
from typing import Any

from rag.chunker import Chunker
from rag.contract import SuccessPayload
from rag.embedder import EmbedError, OllamaEmbedder
from rag.errors import DatabaseError, ReaderError, RagError
from rag.readers import Reader, is_readable, sanitize_text, SourceKind
from rag.repository import ChunkRepository
from rag.settings import Settings


class IngestionPipeline:
    """Coordinates the full document ingestion flow.

    The Laravel side marks the document as `processing` before spawning
    this process; on success we flip it to `ready`, on failure to `failed`
    including the failure reason for the admin panel.
    """

    def __init__(
        self,
        *,
        settings: Settings,
        document_id: int,
        path: str,
        mime_type: str,
        chunker: Chunker | None = None,
        embedder: OllamaEmbedder | None = None,
        repository: ChunkRepository | None = None,
    ) -> None:
        self.settings = settings
        self.document_id = document_id
        self.path = path
        self.mime_type = mime_type
        self.chunker = chunker or self._build_chunker(settings)
        self.embedder = embedder or OllamaEmbedder(settings)
        self.repository = repository

    @staticmethod
    def _build_chunker(settings: Settings) -> Chunker:
        return Chunker(size=settings.chunk_size, overlap=settings.chunk_overlap)

    def run(self) -> SuccessPayload:
        """Execute the ingestion and return the success contract payload."""
        started = time.monotonic()
        repository = self.repository if self.repository is not None else self._connect()

        if not repository.document_exists(self.document_id):
            raise RagError(
                f"Document {self.document_id} not found in catalog; "
                "ingest from the admin panel first.",
                code="validation_error",
            )

        self._validate_mime()

        content = Reader.extract(self._as_path(), self.mime_type)
        content = sanitize_text(content)
        if not is_readable(content):
            msg = "Extracted text is not readily human-readable (binary or scan?)"
            raise ReaderError(msg)

        chunks = self.chunker.chunks(content)
        if not chunks:
            raise ReaderError("Document produced no chunks (no readable content).")

        try:
            result = self.embedder.embed([chunk.content for chunk in chunks])
        except EmbedError as error:
            self._mark_failed(repository, error)
            raise

        repository.insert_chunks(
            document_id=self.document_id,
            contents=[chunk.content for chunk in chunks],
            embeddings=result.embeddings,
            metadata={"chunking": {"size": self.chunker.size, "overlap": self.chunker.overlap}},
            token_counts=self._token_counts(chunks),
        )
        repository.mark_status(self.document_id, "ready")

        duration_ms = int((time.monotonic() - started) * 1000)
        return SuccessPayload(
            document_id=self.document_id,
            chunks=len(chunks),
            embeddings_batches=result.batches,
            duration_ms=duration_ms,
        )

    def _connect(self) -> ChunkRepository:
        connection = ChunkRepository.connect(self.settings)
        self.repository = connection
        return connection

    def _validate_mime(self) -> None:
        supported = {kind.value for kind in SourceKind}
        if self.mime_type not in supported:
            raise ReaderError(
                f"Unsupported mime type '{self.mime_type}'. Supported: {sorted(supported)}",
            )

    def _as_path(self) -> Any:
        from pathlib import Path

        return Path(self.path)

    def _token_counts(self, chunks: Any) -> list[int]:
        return [len(chunk.content.split()) for chunk in chunks]

    def _mark_failed(self, repository: ChunkRepository, error: RagError) -> None:
        try:
            repository.mark_status(self.document_id, "failed", str(error))
        except DatabaseError:
            pass


def run_pipeline(
    *,
    settings: Settings,
    document_id: int,
    path: str,
    mime_type: str,
    repository: ChunkRepository | None = None,
) -> SuccessPayload:
    """Eagerly wired convenience wrapper used by the CLI."""
    return IngestionPipeline(
        settings=settings,
        document_id=document_id,
        path=path,
        mime_type=mime_type,
        repository=repository,
    ).run()


__all__ = ["IngestionPipeline", "run_pipeline"]
