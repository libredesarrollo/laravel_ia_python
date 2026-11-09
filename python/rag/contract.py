"""Structured JSON contract between the Python pipeline and Laravel.

Laravel executes this script as a CLI process. Success and failure are both
reported as a single JSON document so the PHP side never parses prose.

Success shape (stdout, exit code 0):

    {"status": "ok", "document_id": 1, "chunks": 42, "duration_ms": 1832, ...}

Failure shape (stderr, exit code 1):

    {"status": "error", "code": "reader_error", "message": "...", "document_id": 1}
"""

from __future__ import annotations

import json
import sys
from dataclasses import asdict, dataclass
from typing import Any


@dataclass(frozen=True, slots=True)
class SuccessPayload:
    """Payload emitted on a successful ingestion run."""

    document_id: int
    chunks: int
    embeddings_batches: int
    duration_ms: int

    @property
    def as_document(self) -> dict[str, Any]:
        return {"status": "ok", **asdict(self)}


@dataclass(frozen=True, slots=True)
class ErrorPayload:
    """Payload emitted on a failed ingestion run."""

    code: str
    message: str
    document_id: int

    @property
    def as_document(self) -> dict[str, Any]:
        return {
            "status": "error",
            "code": self.code,
            "message": self.message,
            "document_id": self.document_id,
        }


def emit_success(payload: SuccessPayload) -> None:
    """Write the success contract document to stdout."""
    sys.stdout.write(json.dumps(payload.as_document, ensure_ascii=False) + "\n")


def emit_error(payload: ErrorPayload) -> None:
    """Write the failure contract document to stderr."""
    sys.stderr.write(json.dumps(payload.as_document, ensure_ascii=False) + "\n")


__all__ = [
    "ErrorPayload",
    "SuccessPayload",
    "emit_error",
    "emit_success",
]
