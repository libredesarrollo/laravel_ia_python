# python/ — local RAG ingestion pipeline

Sub-proyecto Python del sistema **IA Local RAG**. Es la pieza de *ingesta*:
lee documentos locales, los trocea con solapamiento, genera embeddings contra
el servidor **Ollama local** y los inserta en **PostgreSQL + pgvector**.

Contexto general del sistema completo: `../README.md`.

```
 Flujo de una ingesta
 ────────────────────
 Laravel (Process facade)
    │  .venv/bin/python process_documents.py --document-id N --path … --mime …
    ▼
 process_documents.py ──▶ rag.pipeline.IngestionPipeline
    ├── rag.readers        PDF (pypdf) / TXT / MD / CSV     (solo archivos locales)
    ├── rag.chunker        ventanas ~1000 car. + solape 200
    ├── rag.embedder       POST /api/embed (por lotes, urllib stdlib)
    └── rag.repository     psycopg2 → INSERT chunks + vector, UPDATE status
    ▼
 PostgreSQL (documents, document_chunks) · ciclo de status:
 pending → processing (Laravel) → ready | failed (Python)
```

## Instalación

```bash
# desde la raíz del repo
python3 -m venv python/.venv
python/.venv/bin/pip install -r python/requirements.txt

# modelo necesario en el Ollama local
ollama pull nomic-embed-text        # embeddings (768 dimensiones)
```

## Ejecución

```bash
# a mano (las mismas variables que Laravel inyecta)
export RAG_DB_HOST=127.0.0.1 RAG_DB_PORT=5432
export RAG_DB_DATABASE=rag_local RAG_DB_USERNAME=rag_local RAG_DB_PASSWORD=…
export OLLAMA_BASE_URL=http://127.0.0.1:11434 OLLAMA_EMBED_MODEL=nomic-embed-text

python/.venv/bin/python python/process_documents.py \
    --document-id 1 \
    --path storage/app/documents/manual.pdf \
    --mime application/pdf
```

En el día a día no se ejecuta a mano: Laravel lo lanza vía
`App\Services\LocalRag\LocalRagService` (facade `Process`, con timeout y
entorno mínimo controlado).

## Contrato CLI con Laravel

| Exit | Canal | Contenido |
|---|---|---|
| `0` | stdout | `{"status": "ok", "document_id": …, "chunks": N, "embeddings_batches": B, "duration_ms": ms}` |
| `1` | stderr | `{"status": "error", "code": "<código>", "message": "…", "document_id": N}` |

Códigos (`rag/errors.py`):

| Código | Cuándo se emite |
|---|---|
| `reader_error` | archivo ausente, vacío, >20 MB, mime no soportado, PDF corrupto, texto no legible (¿scan sin OCR?) |
| `embed_error` | Ollama caído, respuesta malformada o dimensión ≠ la esperada |
| `db_error` | conexión/caída PostgreSQL o fallo de inserción (rollback incluido) |
| `validation_error` | documento inexistente en el catálogo |
| `internal_error` | cualquier excepción no clasificada (última línea defensiva del CLI) |

Nunca se filtra un traceback crudo a Laravel: todo error se convierte a JSON
del contrato en `process_documents.main()`.

## Módulos

| Módulo | Responsabilidad |
|---|---|
| `settings.py` | `Settings` dataclass (frozen) resuelta de las variables `RAG_*`/`OLLAMA_*` |
| `contract.py` | payloads de salida + `emit_success`/`emit_error` (stdout/stderr) |
| `errors.py` | `RagError` base + subclases tipadas con código |
| `readers.py` | factory por mime (PDF vía `pypdf`), sanitización y `is_readable` |
| `chunker.py` | `Chunker`: párrafos → frases → ventanas con solapamiento |
| `embedder.py` | `OllamaEmbedder` con transport inyectable (tests sin sockets) |
| `repository.py` | `ChunkRepository` (psycopg2): existencia del doc, inserción de chunks + vectores, cambio de status |
| `pipeline.py` | orquestación end-to-end y gestión de estados del documento |
| `process_documents.py` | CLI: argparse + captura de errores → contrato JSON |

## Tests

```bash
python -m unittest discover -s python/tests
```

Suite con **unittest de stdlib** (no hay pytest): 5 tests de chunking
(overlap, índice), sanitización de texto y batching/dimensiones con un
`FakeTransport` sin sockets. Mantenerla libre de dependencias extra.

## Límites y puntos de cuidado

- **Dimensionalidad es acoplada**: si cambias el modelo de embeddings,
  actualiza `RAG_EMBEDDING_DIMENSIONS` **y** la migración `vector(N)` de
  Laravel — jamás solo uno de los dos.
- `embedder` valida la longitud del vector en cada lote (`_conform`) y falla
  rápido antes de envenenar la tabla de chunks.
- `repository.insert_chunks()` construye un único `INSERT … VALUES …` con
  `CAST(%s AS jsonb)` y `%s::vector`: no modificar sin cubrir con test.
- Los PDF escaneados (sin capa de texto) caen en `is_readable == False` → el
  documento queda `failed` con su reason visible en el panel; el OCR queda
  como iteración futura del producto.
- El venv (`python/.venv/`) está en `.gitignore`; nunca se sube al
  repositorio.

## Licencia

MIT.
