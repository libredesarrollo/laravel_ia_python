"""PostgreSQL repository for document ingestion via psycopg2.

Connects exclusively to the local PostgreSQL server using the same
credentials Laravel passes through environment variables.
"""

from __future__ import annotations

from typing import Any, Final

from rag.errors import DatabaseError

DB_ERROR_ACCESS_DENIED: Final[str] = "28P01"
DB_ERROR_NO_DATABASE: Final[str] = "3D000"

class ChunkRepository:
    """Writes documents, chunks and vectors in transactional batches."""

    def __init__(self, connection: Any) -> None:
        self.connection = connection

    @classmethod
    def connect(cls, settings: Any) -> "ChunkRepository":
        """Open a PostgreSQL connection from pipeline settings."""
        import psycopg2  # Local import keeps failures inside contract scope.

        try:
            connection = psycopg2.connect(
                host=settings.host,
                port=settings.host_port,
                user=settings.host_user,
                password=settings.host_password,
                dbname=settings.host_database,
                connect_timeout=10,
            )
        except Exception as error:  # noqa: BLE001 - normalized as contract error.
            message = f"Cannot connect to PostgreSQL: {error.__class__.__name__}: {error}"
            raise DatabaseError(message) from error
        return cls(connection)

    def insert_chunks(
        self,
        document_id: int,
        contents: list[str],
        embeddings: list[list[float]],
        metadata: dict[str, Any] | None = None,
        token_counts: list[int] | None = None,
    ) -> int:
        """Insert all chunks for one document atomically; rows count returned."""
        if not contents:
            return 0

        row_placeholder: Final[str] = (
            "(%s, %s, %s, CAST(%s AS jsonb), %s, %s::vector)"
        )
        placeholders = ", ".join(row_placeholder for _ in contents)
        rows: list[Any] = []
        for index, (content, embedding) in enumerate(zip(contents, embeddings, strict=True)):
            row = [
                document_id,
                index,
                content,
                json_dumps(metadata or {}),
                token_counts[index] if token_counts else abs(len(content.split())),
                vector_literal(embedding),
            ]
            rows.extend(row)
        statement = f"""
            INSERT INTO document_chunks
                (document_id, chunk_index, content, metadata, token_count, embedding)
            VALUES {placeholders}
        """
        try:
            cursor = self.connection.cursor()
            cursor.execute(statement, rows)
            self.connection.commit()
            return len(contents)
        except Exception as error:  # noqa: BLE001 - normalized as contract error.
            self.connection.rollback()
            message = f"Chunk insertion failed: {error.__class__.__name__}: {error}"
            raise DatabaseError(message) from error

    def mark_status(
        self,
        document_id: int,
        status: str,
        failure_reason: str | None = None,
    ) -> None:
        """Update the document lifecycle status recorded by Laravel."""
        statement = """
            UPDATE documents
               SET status = %s, failure_reason = %s, ingested_at = NOW()
             WHERE id = %s
        """
        try:
            cursor = self.connection.cursor()
            cursor.execute(statement, (status, failure_reason, document_id))
            self.connection.commit()
        except Exception as error:  # noqa: BLE001 - normalized as contract error.
            self.connection.rollback()
            message = f"Status update failed: {error.__class__.__name__}: {error}"
            raise DatabaseError(message) from error

    def document_exists(self, document_id: int) -> bool:
        try:
            cursor = self.connection.cursor()
            cursor.execute("SELECT 1 FROM documents WHERE id = %s", (document_id,))
            return cursor.fetchone() is not None
        except Exception as error:  # noqa: BLE001 - normalized as contract error.
            message = f"Document lookup failed: {error.__class__.__name__}: {error}"
            raise DatabaseError(message) from error


def vector_literal(components: list[float]) -> str:
    """pgvector text literal, e.g. "[0.1,0.2,0.3]"."""
    return "[%s]" % ",".join(repr(float(component)) for component in components)


def json_dumps(value: dict[str, Any]) -> str:
    import json

    return json.dumps(value, ensure_ascii=False)


__all__ = ["ChunkRepository", "vector_literal"]
