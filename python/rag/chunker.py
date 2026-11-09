"""Chunking with paragraph awareness and token-sliding overlap."""

from __future__ import annotations

from dataclasses import dataclass
from typing import Final, Iterator

SentenceSeparators: Final[str] = "\n.!?。؛;:"
_min_chunk_chars: Final[int] = 24


@dataclass(frozen=True, slots=True)
class Chunk:
    """A text fragment ready for embedding and insertion."""

    index: int
    content: str


class Chunker:
    """Produces overlapping fragments near `size` characters each.

    The strategy:
      1. Split the document into paragraphs and keep very large paragraphs
         split by sentence boundaries.
      2. Greedily pack paragraphs into windows of up to `size` characters.
      3. Carry an `overlap`-character tail between consecutive windows so no
         chunk ends mid-idea and retrieval keeps semantic continuity.
    """

    def __init__(self, size: int = 1000, overlap: int = 200) -> None:
        if size <= 0:
            raise ValueError("Chunk size must be greater than zero.")
        if overlap < 0 or overlap >= size:
            raise ValueError("Overlap must be >= 0 and smaller than size.")
        self.size = size
        self.overlap = overlap

    def chunks(self, content: str) -> list[Chunk]:
        """Return all chunks for a document body."""
        if not content.strip():
            return []

        chunks: list[Chunk] = []
        previous_tail = ""
        for window in self._windows(content):
            emitted = f"{previous_tail}{window}".strip()
            if emitted:
                chunks.append(Chunk(index=len(chunks), content=emitted))
            previous_tail = window[-self.overlap:] if self.overlap else ""
        return chunks

    def _windows(self, content: str) -> Iterator[str]:
        """Yield windows of approximately `size` characters."""
        paragraphs = self._paragraphs(content)
        buffer = ""
        for paragraph in paragraphs:
            if len(paragraph) > self.size:
                if buffer:
                    yield buffer
                    buffer = ""
                yield from self._split_large(paragraph)
                continue
            if len(buffer) + len(paragraph) + 1 > self.size:
                yield buffer
                buffer = paragraph
                continue
            buffer = f"{buffer}\n\n{paragraph}".strip("\n\n")
        if buffer:
            yield buffer

    def _paragraphs(self, content: str) -> list[str]:  # noqa: C901 - readability first.
        """Split on blank lines; split oversized single lines by sentence."""
        paragraphs: list[str] = []
        for block in "\n\n".join([p.strip() for p in content.split("\n\n") if p.strip()]).split(
            "\n\n",
        ):
            if len(block) > self.size:
                paragraphs.extend(self._split_sentences(block))
            else:
                paragraphs.append(block)
        return paragraphs

    def _split_sentences(self, block: str) -> list[str]:
        """Split near sentence boundaries, keeping sentences intact."""
        sentences: list[str] = []
        current: list[str] = []
        for char in block:
            current.append(char)
            if char in SentenceSeparators:
                sentences.append("".join(current))
                current = []
        if current:
            sentences.append("".join(current))

        merged: list[str] = []
        buffer = ""
        for sentence in sentences:
            if len(buffer) + len(sentence) > self.size and buffer:
                merged.append(buffer)
                buffer = sentence
                continue
            buffer += sentence
        if buffer:
            merged.append(buffer)
        return merged

    def _split_large(self, paragraph: str) -> list[str]:
        return [part.strip() for part in self._split_sentences(paragraph)]


__all__ = ["Chunk", "Chunker", "_min_chunk_chars"]
