// Corporate chat: minimal vanilla-JS client with SSE-style streaming.
// Reads the token stream sent by Laravel's StreamedResponse -> Ollama.

const messagesBox = document.getElementById('messages');
const citationsBox = document.getElementById('citations');
const citationsList = document.getElementById('citationsList');
const form = document.getElementById('chatForm');
const questionInput = document.getElementById('question');
const sendButton = document.getElementById('sendButton');
const ragStatus = document.getElementById('ragStatus');

function bubble(role, content = '') {
    const container = document.createElement('div');
    container.className = role === 'user'
        ? 'flex justify-end'
        : 'flex justify-start';

    const bubble = document.createElement('div');
    bubble.className = role === 'user'
        ? 'max-w-2xl rounded-2xl rounded-br-sm bg-cyan-950/60 border border-cyan-900/60 px-4 py-2.5 text-sm text-cyan-100 whitespace-pre-wrap'
        : 'max-w-2xl rounded-2xl rounded-bl-sm bg-slate-800/70 border border-slate-700 px-4 py-2.5 text-sm text-slate-100 whitespace-pre-wrap';

    bubble.textContent = content;
    container.appendChild(bubble);
    messagesBox.appendChild(container);
    scrollDown();

    return bubble;
}

function scrollDown() {
    messagesBox.scrollTop = messagesBox.scrollHeight;
}
function clearCitations() {
    citationsBox.classList.add('hidden');
    citationsList.innerHTML = '';
}

function renderCitations(sources) {
    citationsList.innerHTML = '';
    for (const source of sources) {
        const item = document.createElement('li');
        item.textContent = `«${source.document}» · fragmento ${source.chunk} · similitud ${source.similarity}`;
        citationsList.appendChild(item);
    }
    citationsBox.classList.remove('hidden');
}

// Minimal markdown renderer, vanilla (no external CDN).
function renderInlineMarkdown(text) {
    return text
        .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
        .replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>')
        .replace(/\*([^*]+)\*/g, '<em>$1</em>')
        .replace(/`([^`]+)`/g, '<code class="rounded bg-slate-700/70 px-1 py-0.5 text-[12px]">$1</code>')
        .replace(/(https?:\/\/[^\s<]+)/g, '<a class="text-cyan-400 hover:underline" href="$1">$1</a>');
    // NOTE: bullet lists are intentionally deferred; answer formatting relies
    // on whitespace rendering to keep the bundle dependency-free.
}

form.addEventListener('submit', async (event) => {
    event.preventDefault();

    const question = questionInput.value.trim();
    if (!question) return;

    questionInput.value = '';
    sendButton.disabled = true;

    bubble('user', question);
    clearCitations();

    const answer = bubble('assistant', '▋');

    try {
        const response = await fetch(form.action, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'text/event-stream',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({
                question,
                document_id: document.getElementById('document_id').value || null,
            }),
        });

        if (!response.ok) {
            const payload = await response.json().catch(() => null);
            const message = payload?.message || 'Error al iniciar el stream.';

            answer.textContent = `⚠️ ${message}`;
            scrollDown();
            return;
        }

        const reader = response.body.getReader();
        const decoder = new TextDecoder();
        let buffer = '';
        let assembled = '';

        while (true) {
            const { value, done } = await reader.read();
            if (done) break;

            buffer += decoder.decode(value, { stream: true });

            const frameIndex = buffer.lastIndexOf('\n\n');
            if (frameIndex === -1) continue;

            const complete = buffer.slice(0, frameIndex);
            buffer = buffer.slice(frameIndex + 2);

            for (const block of complete.split('\n\n')) {
                const eventMatch = block.match(/^event:\s*(.+)$/m);
                const dataMatch = block.match(/^data:\s*(.+)$/m);
                if (!eventMatch || !dataMatch) continue;

                const eventName = eventMatch[1].trim();

                try {
                    const payload = JSON.parse(dataMatch[1]);

                    if (eventName === 'token' && typeof payload.t === 'string') {
                        assembled += payload.t;
                        answer.textContent = assembled;
                        scrollDown();
                    } else if (eventName === 'sources') {
                        renderCitations(payload);
                    }
                } catch {
                    // Ignore malformed frame fragments, never break the stream.
                }
            }
        }

        if (assembled.length > 0) {
            answer.innerHTML = renderInlineMarkdown(assembled);
            scrollDown();
        } else {
            answer.textContent = '(el modelo no devolvió texto. Revisa `OLLAMA_CHAT_MODEL` en .env).';
        }
    } catch (error) {
        answer.textContent = `⚠️ Error de conexión con el servidor local: ${error}`;
    } finally {
        scrollDown();
        sendButton.disabled = false;
        questionInput.focus();
    }
});
