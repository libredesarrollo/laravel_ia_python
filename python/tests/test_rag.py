"""stdlib-based tests for the local RAG pipeline (python3 -m unittest)."""

from __future__ import annotations

import unittest

from rag.chunker import Chunker
from rag.embedder import OllamaEmbedder
from rag.readers import sanitize_text
from rag.settings import Settings


class ChunkerTests(unittest.TestCase):
    def test_chunks_overlap_and_respect_size(self) -> None:
        chunker = Chunker(size=120, overlap=30)
        body = "\n\n".join(
            f"Párrafo {index}. " + "Lore mútitlínea: " + (str(index) * 60)
            for index in range(6)
        )
        chunks = chunker.chunks(body)

        self.assertGreater(len(chunks), 1)
        for index, chunk in enumerate(chunks):
            self.assertEqual(chunk.index, index)
        # Consecutive chunks share the overlap tail.
        self.assertTrue(chunks[1].content.startswith(chunks[0].content[-30:]))

    def test_empty_document_yields_no_chunks(self) -> None:
        self.assertEqual(Chunker().chunks("   \n\n  "), [])


class SanitizeTests(unittest.TestCase):
    def test_collapses_blank_lines(self) -> None:
        self.assertEqual(sanitize_text("a\n\n\n\n\nb"), "a\n\nb")


class FakeTransport:
    """Echoes one embedding per item of the requested batch window."""

    def __init__(self, vector: list[float]) -> None:
        self.vector = vector
        self.calls: list[dict[str, object]] = []

    def post(self, path: str, body: dict[str, object]) -> dict[str, object]:
        self.calls.append({"path": path, "body": body})
        window = body["input"]
        assert isinstance(window, list)
        return {"embeddings": [list(self.vector) for _ in window]}


class EmbedderTests(unittest.TestCase):
    def _settings(self, batch_size: int = 2) -> Settings:
        return Settings(
            host="127.0.0.1",
            port=5432,
            user="user",
            password="pw",
            database="rag_local",
            ollama_base_url="http://127.0.0.1:11434",
            embedding_model="nomic-embed-text",
            embedding_dimensions=3,
            embedding_batch_size=batch_size,
            chunk_size=1000,
            chunk_overlap=200,
        )

    def test_batches_until_end(self) -> None:
        transport = FakeTransport([0.1, 0.2, 0.3])
        embedder = OllamaEmbedder(self._settings(), transport=transport)

        result = embedder.embed(["a", "b", "c"])

        self.assertEqual(result.batches, 2)
        self.assertEqual(len(result.embeddings), 3)

    def test_rejects_dimension_mismatch(self) -> None:
        from rag.errors import EmbedError

        transport = FakeTransport([0.1, 0.2])
        embedder = OllamaEmbedder(self._settings(), transport=transport)

        with self.assertRaises(EmbedError):
            embedder.embed(["texto"])


if __name__ == "__main__":
    unittest.main()
