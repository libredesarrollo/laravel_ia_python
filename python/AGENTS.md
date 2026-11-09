# Agent rules — python/ (sub-proyecto de ingesta RAG local)

Estas reglas aplican a todo archivo bajo `python/`. Las reglas del proyecto
Laravel están en `../AGENTS.md`; el sistema completo es local-only: jamás se
contactan servicios externos (OpenAI, Anthropic, embeddings en la nube, etc.)
ni se añaden dependencias que no sean de ejecución en `localhost`.

## Identidad del subproyecto

- Pipeline de ingesta ejecutado por Laravel a través de
  `App\Services\LocalRag\LocalRagService` → `Process` facade → venv.
- Un único binario de entrada: `python/process_documents.py`. No crear otros
  CLIs ni daemons dentro de `python/`; el producto no tiene servicios
  residentes.
- Dependencias permitidas (`requirements.txt`): solo ejecución
  local — `psycopg2-binary`, `pypdf`. La parte HTTP de Ollama usa `urllib`
  de stdlib a propósito; no añadir `requests` ni `httpx` sin discusión.

## Contrato JSON (invariante)

- stdout (exit 0) y stderr (exit 1) son JSON estricto: ver
  `rag/contract.py` (`SuccessPayload` / `ErrorPayload`).
- Esquema de error codes: `reader_error`, `embed_error`, `db_error`,
  `validation_error`, `internal_error`. Añadir un código exige tocar:
  `rag/errors.py` → el punto de fallo → el README del subproyecto → los tests
  Pest de Laravel que simulan el proceso.
- Prohibido dejar que una excepción cruda llegue al stdout/stderr sin
  empaquetar en el contrato; el CLI (`main()`) es la última línea defensiva.

## Convenciones de código

- `from __future__ import annotations` al inicio de cada módulo; type hints en
  todas las firmas; dataclasses `frozen=True, slots=True` para settings y DTOs.
- Errores de dominio: subclases de `rag.errors.RagError` con `code` — nada de
  `Exception` cruda en las capas communications/pipeline.
- I/O de red limitada a Ollama (`urllib`) y BD (`psycopg2`); con timeouts
  explícitos (`_TIMEOUT_SECONDS`, `connect_timeout`).
- Los tests viven en `python/tests/` con unittest de stdlib. Correrlos:
  `python3 -m unittest discover -s python/tests`. Mantenerlos sin pytest.
- Cuando tocas el `repository`, cubre el SQL construido con un test
  (placeholders, `::vector`, `jsonb`) — es lo más frágil del paquete.

## Invariantes esquemáticos compartidos con Laravel

- Dimensión de embeddings (`Settings.embedding_dimensions`) debe coincidir con
  `config('rag.embeddings.dimensions')` de Laravel y con la migración
  `vector(N)`. Cambiar el modelo = migración nueva de Laravel **y** actualizar
  aquí — nunca un solo lado.
- El status del documento es la API entre procesos: Python escribe
  `ready` / `failed` (+ `failure_reason`) y Laravel escribe
  `processing`/`pending`. No mover esa responsabilidad a otro lado.

## Runbook del agente

1. Ejecuta `python3 -m unittest discover -s python/tests` (5 tests en verde)
   tras cualquier cambio en `python/rag/*`.
2. Si tocas `python/rag/contract.py` o `repository.py`, añade/ajusta también
   los tests Pest de Laravel (`vendor/bin/pest tests/Unit/LocalRagServiceTest.php`)
   porque ambos lados comparten el mismo contrato.
3. Docstrings y comentarios en español o inglés — elegir el idioma dominante
   del módulo; mezclar ing/francés mezclado confunde al equipo.
