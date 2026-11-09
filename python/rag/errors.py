"""Errors raised by the local RAG pipeline.

Errors are reported to Laravel as structured JSON on stderr via
:func:`python.rag.contract.error_payload`, never as raw tracebacks.
"""

from __future__ import annotations

from enum import StrEnum

READER_CODE: str = "reader_error"
EMBED_CODE: str = "embed_error"
DB_CODE: str = "db_error"
VALIDATION_CODE: str = "validation_error"


class RagError(Exception):
    """Base error carrying a machine-readable message code."""

    def __init__(
        self,
        message: str,
        code: str = "error",
    ) -> None:
        super().__init__(message)
        self.code = code


class ReaderError(RagError):
    def __init__(self, message: str) -> None:
        super().__init__(message, READER_CODE)


class EmbedError(RagError):
    def __init__(self, message: str) -> None:
        super().__init__(message, EMBED_CODE)


class DatabaseError(RagError):
    def __init__(self, message: str) -> None:
        super().__init__(message, DB_CODE)


class ValidationError(RagError):
    def __init__(self, message: str) -> None:
        super().__init__(message, VALIDATION_CODE)


__all__ = [
    "DatabaseError",
    "EmbedError",
    "RagError",
    "ReaderError",
    "ValidationError",
]
