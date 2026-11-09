#!/usr/bin/env python3
"""Local RAG ingestion CLI.

Executed by Laravel as an external process (Laravel `Process` facade with the
venv interpreter). Contract:

    success (exit 0, stdout):
        {"status": "ok", "document_id": .., "chunks": .., ...}
    failure (exit 1, stderr):
        {"status": "error", "code": "...", "message": "...", "document_id": ..}

Usage:
    process_documents.py --document-id 1 --path storage/documents/x.pdf --mime application/pdf
"""

from __future__ import annotations

import argparse
import sys

from rag.contract import ErrorPayload, emit_error, emit_success
from rag.errors import RagError
from rag.pipeline import run_pipeline
from rag.settings import Settings

_EXIT_OK: int = 0
_EXIT_ERROR: int = 1


def build_parser() -> argparse.ArgumentParser:
    parser = argparse.ArgumentParser(description="Local RAG document ingestion.")
    parser.add_argument("--document-id", type=int, required=True)
    parser.add_argument("--path", type=str, required=True)
    parser.add_argument("--mime", type=str, required=True)
    parser.add_argument("--title", type=str, default="Document")
    return parser


def main(argv: list[str] | None = None) -> int:
    arguments = build_parser().parse_args(argv)
    settings = Settings.from_environment()

    try:
        payload = run_pipeline(
            settings=settings,
            document_id=arguments.document_id,
            path=arguments.path,
            mime_type=arguments.mime,
        )
    except RagError as error:
        emit_error(
            ErrorPayload(
                code=error.code,
                message=str(error),
                document_id=arguments.document_id,
            ),
        )
        return _EXIT_ERROR
    except Exception as error:  # noqa: BLE001 - last-resort contract error.
        emit_error(
            ErrorPayload(
                code="internal_error",
                message=f"Unexpected failure: {error.__class__.__name__}: {error}",
                document_id=arguments.document_id,
            ),
        )
        return _EXIT_ERROR

    emit_success(payload)
    return _EXIT_OK


if __name__ == "__main__":
    sys.exit(main())
