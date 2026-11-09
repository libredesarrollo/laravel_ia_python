"""Document extraction adapters: PDF, plain text and Markdown.

Readers only touch local files; none of them performs network I/O.
"""

from __future__ import annotations

import io
from enum import StrEnum
from pathlib import Path

from rag.errors import ReaderError

MAX_BYTES: int = 20 * 1024 * 1024  # 20 MiB safety cap for uploaded files.


class SourceKind(StrEnum):
    PDF = "application/pdf"
    PLAIN_TEXT = "text/plain"
    MARKDOWN = "text/markdown"
    CSV = "text/csv"


class Reader:
    """Extracts plain text from a supported local file."""

    @staticmethod
    def extract(path: Path, mime_type: str) -> str:
        """Return the full text content of the file at `path`."""
        if not path.is_file():
            raise ReaderError(f"File not found: {path}")

        size = path.stat().st_size
        if size == 0:
            raise ReaderError(f"File is empty: {path}")
        if size > MAX_BYTES:
            raise ReaderError(f"File exceeds {MAX_BYTES} bytes: {path}")

        try:
            match mime_type:
                case SourceKind.PDF:
                    return PdfTextReader.extract(path)
                case _:
                    return PlainTextReader.extract(path)
        except ReaderError:
            raise
        except Exception as exc:  # noqa: BLE001 - report as contract error.
            msg = f"Unexpected reader failure for {path.name}: {exc}"
            raise ReaderError(msg) from exc


class PdfTextReader:
    """PDF extraction using pypdf (local, no out-of-process services)."""

    @staticmethod
    def extract(path: Path) -> str:
        from pypdf import PdfReader

        try:
            reader = PdfReader(str(path))
        except Exception as exc:  # noqa: BLE001 - any parser failure is a reader error.
            msg = f"Unable to parse PDF {path.name}: {exc}"
            raise ReaderError(msg) from exc

        pages: list[str] = []
        for index, page in enumerate(reader.pages, start=1):
            text = page.extract_text() or ""
            pages.append(f"[Page {index}]\n{text.strip()}")

        return "\n\n".join(pages)


class PlainTextReader:
    """UTF-8 text / Markdown / CSV extraction."""

    @staticmethod
    def extract(path: Path) -> str:
        try:
            with open(path, "r", encoding="utf-8", errors="replace") as handle:
                return handle.read()
        except OSError as exc:
            msg = f"Unable to read {path.name}: {exc}"
            raise ReaderError(msg) from exc


def guess_mime_type(path: Path) -> str:
    """Map a file suffix to one of the supported source kinds."""
    match path.suffix.lower():
        case ".pdf":
            return SourceKind.PDF.value
        case ".md" | ".mdown" | ".markdown":
            return SourceKind.MARKDOWN.value
        case ".csv":
            return SourceKind.CSV.value
        case ".txt" | ".text" | ".log":
            return SourceKind.PLAIN_TEXT.value
        case _:
            return SourceKind.PLAIN_TEXT.value


def sanitize_text(content: str) -> str:
    """Normalize whitespace before chunking (NFC + collapse blank lines)."""
    import unicodedata

    text = unicodedata.normalize("NFC", content)
    lines = [line.rstrip() for line in text.splitlines()]
    normalized: list[str] = []
    blank = 0
    for line in lines:
        if line == "":
            blank += 1
            if blank <= 1:
                normalized.append(line)
            continue
        blank = 0
        normalized.append(line)
    return "\n".join(normalized).strip()


def is_readable(content: str) -> bool:
    """Cheap sanity check that we extracted mostly-human text."""
    io_text = io.StringIO(content)
    sample = io_text.read(2048)
    if not sample:
        return False
    printable = sum(1 for character in sample if character.isprintable() or character in "\n\r\t")
    return printable / len(sample) > 0.7


__all__ = [
    "MAX_BYTES",
    "PdfTextReader",
    "PlainTextReader",
    "Reader",
    "SourceKind",
    "guess_mime_type",
    "is_readable",
    "sanitize_text",
]
