# Automatización con IA Local y RAG para Empresas
## Sistema 100% on-premise — cero fugas de datos

Pipeline completo de administración de conocimiento y chat semántico que corre
íntegramente en la infraestructura de la empresa:

```
┌────────────── Usuario (panel) ──────────────┐
│  Blade + Tailwind 4 · vanilla JS (SSE)      │
└──────────────┬──────────────────────────────┘
               │
┌──────────────▼──────────────────┐   ┌─────────────────────────┐
│  Laravel 13 (PHP 8.5)           │   │  Ollama (localhost)     │
│  Orquestación · Retrieval · RAG │──▶│  nomic-embed-text (768) │
│  Process facade → CLI Python    │   │  gemma4:12b (chat)      │
└──────┬──────────────────┬───────┘   └─────────────────────────┘
       │                  │
       │          ┌───────▼────────┐
       │          │  Python venv   │  readers → chunking → embeddings
       │          │  (psycopg2)    │──────────────────────┐
       │          └────────────────┘                      │
┌──────▼───────────────────────────────────────────────────▼────┐
│  PostgreSQL + pgvector · documents + document_chunks(vector)  │
│  Índice HNSW · similitud coseno (<=>)                          │
└────────────────────────────────────────────────────────────────┘
```

**Garantía de privacidad:** no se contacta ningún servicio externo (OpenAI,
Anthropic, cloud embeddings, etc.). El único flujo externo de Laravel es un
subproceso Python local que solo habla con PostgreSQL y Ollama en `localhost`.

---

## Stack

| Capa | Tecnología | Rol |
|---|---|---|
| Panel y API | Laravel 13 · PHP 8.5 · Tailwind 4 · Vite | Vistas, validación, orquestación, streaming SSE |
| IA local | Ollama (`nomic-embed-text` 768d · `gemma4:12b`) | Embeddings y generación de texto |
| Ingesta | Python 3.14 (venv) · `pypdf` · `psycopg2-binary` | Lectura PDF/texto, chunking, inserción de vectores |
| Búsqueda | PostgreSQL 18 + `pgvector` | `embedding vector(768)`, índice HNSW cosine |

## Estructura del proyecto

```
app/
├── Casts/VectorCast.php               # pgvector text ⇄ objeto Vector
├── Console/Commands/RagIngestCommand  # php artisan rag:ingest
├── Enums/DocumentStatus.php           # pending|processing|ready|failed
├── Exceptions/                        # LocalRagException, RetrievalException…
├── Http/Controllers/Admin/
│   ├── ChatController.php             # SSE stream + status Ollama
│   └── DocumentController.php         # subida, ingesta, borrado
├── Http/Requests/StoreDocumentRequest # mimes/tamaño whitelisted
├── Models/                            # Document, DocumentChunk (scope nearestNeighbors)
├── Services/
│   ├── LocalRag/LocalRagService.php   # ejecución de Python + contrato JSON
│   ├── Retrieval/RetrievalService.php # coseno <=> top-k sobre pgvector
│   ├── Chat/ChatService.php           # prompt aumentado con citas
│   └── LocalOllama/OllamaClient.php   # /api/embed + /api/chat (stream)
└── Values/Vector.php                  # value object inmutable
config/rag.php                         # toda la paramétrica del pipeline
python/
├── process_documents.py               # CLI entrypoint
├── rag/{pipeline,readers,chunker,embedder,repository,contract,errors,settings}.py
├── tests/test_rag.py                  # unittest (stdlib)
└── requirements.txt                   # solo pypdf + psycopg2-binary
database/migrations/                   # extensión, documents, chunks (+HNSW)
resources/views/{layouts,admin}/…      # panel corporativo
```

## Puesta en marcha (máquina limpia)

### 1. Requisitos previos (systema)

```bash
# PostgreSQL 18 con pgvector disponible
sudo apt install postgresql postgresql-server-dev-18 postgresql-18-pgvector
# (en distros sin paquete: compilar https://github.com/pgvector/pgvector)

# Ollama en ejecución local
curl -fsSL https://ollama.com/install.sh | sh
ollama pull nomic-embed-text   # embeddings (768 dimensiones)
ollama pull gemma4:12b         # generación del chat
```

### 2. Base de datos

```sql
CREATE USER rag_local WITH PASSWORD 'elige_una_password';
CREATE DATABASE rag_local OWNER rag_local;
\c rag_local
CREATE EXTENSION vector;   -- Laravel también la ejecuta en la migración
```

### 3. Aplicación Laravel + Python

```bash
cp .env.example .env && nano .env          # credenciales DB_* y modelos OLLAMA_*
composer install
php artisan key:generate
php artisan migrate                        # crea documents, chunks, HNSW

python3 -m venv python/.venv
python/.venv/bin/pip install -r python/requirements.txt

npm install && npm run build
php artisan serve                          # → http://localhost:8000 → admin/chat
```

## Uso diario

1. **Subir documento**: panel → *Base documental* → *Subir documento*
   (PDF/TXT/MD/CSV, máx. 20 MB, se deduplica por SHA-256).
2. **Vectorizar**: botón *Ingesta (Python)* o from CLI:

   ```bash
   php artisan rag:ingest {id}    # documento concreto
   php artisan rag:ingest all     # todos los pending/failed
   ```

3. **Consultar**: panel → *Chat corporativo*. La respuesta llega en streaming
   (SSE) con la **lista de fuentes** recuperadas (documento, fragmento,
   similitud coseno).

### Comandos de verificación

```bash
php artisan test --compact             # suite PHP (Pest) — 14 tests
python -m unittest discover -s python/tests   # suite Python — 5 tests
vendor/bin/pint app database tests     # formateo PHP
php artisan rag:ingest --help          # parámetros de ingesta
```

## Contrato Laravel ⇄ Python

Laravel ejecuta `python/.venv/bin python python/process_documents.py …` con un
entorno mínimo (`RAG_DB_*`, `OLLAMA_*`, `RAG_CHUNK_*`). El intercambio de datos
es JSON estricto, definido en `python/rag/contract.py`:

```jsonc
// éxito (exit 0, stdout)
{"status": "ok", "document_id": 1, "chunks": 42, "embeddings_batches": 2, "duration_ms": 1832}

// fallo (exit 1, stderr)
{"status": "error", "code": "embed_error", "message": "…", "document_id": 1}
```

Códigos de error: `reader_error`, `embed_error`, `db_error`,
`validation_error`, `internal_error`.

## Configuración principal (config/rag.php)

| Variable | Defecto | Descripción |
|---|---|---|
| `RAG_DATABASE_CONNECTION` | `database.default` | Conexión PostgreSQL dedicada del RAG |
| `RAG_PYTHON_BINARY` | `python` | Interprete que ejecuta Laravel |
| `RAG_CHUNK_SIZE` | `1000` | Tamaño objetivo de fragmento (caracteres) |
| `RAG_CHUNK_OVERLAP` | `200` | Solapamiento entre fragmentos |
| `RAG_EMBEDDING_DIMENSIONS` | `768` | Dimensión de `vector(...)` (modelo Ollama) |
| `RAG_TOP_K` | `5` | Fragmentos recuperados por consulta |
| `RAG_MIN_SIMILARITY` | `0.0` | Filtro de similitud coseno mínima |
| `OLLAMA_BASE_URL` | `http://127.0.0.1:11434` | Servidor local de Ollama |
| `OLLAMA_CHAT_MODEL` | `gemma4:12b` | Modelo de generación del chat |

## Estado del producto y límites conocidos

- **Panel sin autenticación** durante el desarrollo del núcleo. Rutas bajo
  `routes/web.php` grupo `admin.*` preparadas para añadir middleware `auth` al
  empaquetar.
- La columna `embedding` y el índice HNSW se aplican **solo** si el driver es
  `pgsql`; el suite de tests funciona con SQLite sin pgvector.
- Markdown básico en el chat (negritas, cursiva, `código`, enlaces). Listas y
  tablas pendientes de iteración.

## Licencia

MIT.
