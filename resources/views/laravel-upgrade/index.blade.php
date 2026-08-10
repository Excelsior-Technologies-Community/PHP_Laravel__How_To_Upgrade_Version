<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laravel Version Upgrader</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .terminal {
            background: #0d1117;
            color: #c9d1d9;
            font-family: 'Courier New', Courier, monospace;
            font-size: 12px;
            line-height: 1.6;
            padding: 16px;
            border-radius: 8px;
            overflow-y: auto;
            max-height: 480px;
            white-space: pre-wrap;
            word-break: break-all;
        }
        .terminal .line-ok     { color: #3fb950; }
        .terminal .line-err    { color: #f85149; }
        .terminal .line-info   { color: #58a6ff; }
        .terminal .line-warn   { color: #d29922; }
        .terminal .line-dim    { color: #6e7681; }
        .terminal .cursor {
            display: inline-block;
            width: 8px;
            height: 14px;
            background: #c9d1d9;
            animation: blink 1s step-end infinite;
            vertical-align: text-bottom;
            margin-left: 2px;
        }
        @keyframes blink { 50% { opacity: 0; } }
    </style>
</head>
<body class="bg-gray-100 min-h-screen">
<div class="max-w-5xl mx-auto py-10 px-4 space-y-6">

    {{-- Header --}}
    <div class="bg-white rounded-xl shadow p-6 flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Laravel Version Upgrader</h1>
            <p class="text-sm text-gray-500 mt-1">Real-time upgrade — same as running composer in CMD</p>
        </div>
        <div class="text-right">
            <p class="text-xs text-gray-400 uppercase tracking-wide mb-1">Installed Version</p>
            <span class="text-3xl font-extrabold text-indigo-600">v{{ $currentVersion }}</span>
        </div>
    </div>

    {{-- Upgrade Form --}}
    <div class="bg-white rounded-xl shadow p-6">
        <h2 class="text-lg font-semibold text-gray-800 mb-4">Select Target Version</h2>

        <form id="upgradeForm" class="space-y-4">
            @csrf
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                @foreach($availableVersions as $version => $label)
                    @php $isCurrent = str_starts_with($currentVersion, $version . '.'); @endphp
                    <label class="relative {{ $isCurrent ? 'cursor-not-allowed' : 'cursor-pointer' }}">
                        <input type="radio" name="target_version" value="{{ $version }}"
                               class="sr-only peer" {{ $isCurrent ? 'disabled' : '' }}>
                        <div class="border-2 rounded-lg p-4 text-center select-none transition
                            {{ $isCurrent
                                ? 'border-indigo-500 bg-indigo-50'
                                : 'border-gray-200 hover:border-indigo-400 peer-checked:border-indigo-600 peer-checked:bg-indigo-50' }}">
                            <p class="text-lg font-bold {{ $isCurrent ? 'text-indigo-600' : 'text-gray-800' }}">
                                Laravel {{ $version }}
                            </p>
                            <p class="text-xs mt-1 {{ $isCurrent ? 'text-indigo-400' : 'text-gray-400' }}">
                                {{ $isCurrent ? '✓ Current' : $label }}
                            </p>
                        </div>
                    </label>
                @endforeach
            </div>

            <button type="submit" id="upgradeBtn"
                class="w-full bg-indigo-600 text-white font-semibold py-3 rounded-lg hover:bg-indigo-700 transition disabled:opacity-50 disabled:cursor-not-allowed">
                🚀 Start Upgrade
            </button>
        </form>

        {{-- Terminal --}}
        <div id="terminalWrap" class="mt-5 hidden">
            {{-- Terminal title bar --}}
            <div class="flex items-center justify-between bg-gray-800 rounded-t-lg px-4 py-2">
                <div class="flex gap-1.5">
                    <span class="w-3 h-3 rounded-full bg-red-500"></span>
                    <span class="w-3 h-3 rounded-full bg-yellow-400"></span>
                    <span class="w-3 h-3 rounded-full bg-green-500"></span>
                </div>
                <span id="termTitle" class="text-xs text-gray-400 font-mono">composer update laravel/framework</span>
                <span id="termStatus" class="text-xs font-semibold text-yellow-400">● Running</span>
            </div>

            {{-- Progress bar --}}
            <div class="bg-gray-700 h-1">
                <div id="progressBar" class="h-1 bg-indigo-500 transition-all duration-700" style="width:0%"></div>
            </div>

            {{-- Output --}}
            <div id="terminal" class="terminal rounded-b-lg"><span id="cursor" class="cursor"></span></div>

            {{-- Stats --}}
            <div class="flex gap-4 mt-2 text-xs text-gray-400 font-mono px-1">
                <span>Lines: <span id="lineCount">0</span></span>
                <span>Elapsed: <span id="elapsed">0s</span></span>
            </div>
        </div>
    </div>

    {{-- Recent Upgrades --}}
    <div class="bg-white rounded-xl shadow p-6">
        <h2 class="text-lg font-semibold text-gray-800 mb-4">Recent Upgrades</h2>

        @forelse($recentUpgrades as $upgrade)
            <div class="border border-gray-100 rounded-lg p-4 mb-3 last:mb-0">
                <div class="flex items-center justify-between flex-wrap gap-2">
                    <div class="flex items-center gap-2 flex-wrap">
                        <code class="text-sm bg-gray-100 text-gray-700 px-2 py-0.5 rounded">v{{ $upgrade->current_version }}</code>
                        <span class="text-gray-400 text-sm">→</span>
                        <code class="text-sm bg-indigo-50 text-indigo-700 px-2 py-0.5 rounded">v{{ $upgrade->target_version }}</code>

                        @if($upgrade->status === 'completed')
                            <span class="px-2 py-0.5 text-xs font-semibold rounded-full bg-green-100 text-green-700">✓ Completed</span>
                        @elseif($upgrade->status === 'failed')
                            <span class="px-2 py-0.5 text-xs font-semibold rounded-full bg-red-100 text-red-700">✗ Failed</span>
                        @elseif($upgrade->status === 'running')
                            <span class="px-2 py-0.5 text-xs font-semibold rounded-full bg-yellow-100 text-yellow-700 animate-pulse">⟳ Running</span>
                        @else
                            <span class="px-2 py-0.5 text-xs font-semibold rounded-full bg-gray-100 text-gray-600">Pending</span>
                        @endif
                    </div>
                    <div class="text-right text-xs text-gray-400">
                        <div>{{ $upgrade->created_at->diffForHumans() }}</div>
                        @if($upgrade->completed_at)
                            <div>{{ $upgrade->started_at->diffInSeconds($upgrade->completed_at) }}s duration</div>
                        @endif
                    </div>
                </div>

                @if($upgrade->output)
                    <details class="mt-3">
                        <summary class="text-xs {{ $upgrade->status === 'failed' ? 'text-red-500' : 'text-gray-500' }} cursor-pointer hover:underline">
                            Show output
                        </summary>
                        <div class="mt-2 terminal rounded-lg text-xs max-h-48">{{ $upgrade->output }}</div>
                    </details>
                @endif
            </div>
        @empty
            <p class="text-sm text-gray-400 text-center py-8">No upgrade history yet.</p>
        @endforelse
    </div>

</div>

<script>
const form        = document.getElementById('upgradeForm');
const btn         = document.getElementById('upgradeBtn');
const termWrap    = document.getElementById('terminalWrap');
const terminal    = document.getElementById('terminal');
const cursor      = document.getElementById('cursor');
const termStatus  = document.getElementById('termStatus');
const progressBar = document.getElementById('progressBar');
const lineCountEl = document.getElementById('lineCount');
const elapsedEl   = document.getElementById('elapsed');

let startTime, elapsedTimer, lineCount = 0;

// Strip ANSI escape codes
function stripAnsi(str) {
    return str.replace(/\x1B\[[0-9;?]*[A-Za-z]/g, '')
              .replace(/\x1B\][^\x07]*\x07/g, '')
              .replace(/\x1B[@-_][0-?]*[ -/]*[@-~]/g, '');
}

// Colorize lines like a real terminal
function colorizeLine(line) {
    const span = document.createElement('span');
    if (/^\s*-\s+\w/.test(line) && /Installing|Upgrading|Downgrading/.test(line)) {
        span.className = 'line-info';
    } else if (/error|failed|exception/i.test(line)) {
        span.className = 'line-err';
    } else if (/warning|warn/i.test(line)) {
        span.className = 'line-warn';
    } else if (/✔|done|success|completed|installed|upgraded/i.test(line)) {
        span.className = 'line-ok';
    } else if (/^Loading|^Updating|^Writing|^Generating|^Package operations/i.test(line.trim())) {
        span.className = 'line-dim';
    }
    span.textContent = line;
    return span;
}

function appendOutput(text) {
    const clean = stripAnsi(text);
    const lines = clean.split('\n');
    lines.forEach((line, i) => {
        if (i < lines.length - 1) {
            terminal.insertBefore(colorizeLine(line), cursor);
            terminal.insertBefore(document.createTextNode('\n'), cursor);
            lineCount++;
        } else if (line) {
            terminal.insertBefore(colorizeLine(line), cursor);
        }
    });
    lineCountEl.textContent = lineCount;
    terminal.scrollTop = terminal.scrollHeight;
}

function startElapsed() {
    startTime = Date.now();
    elapsedTimer = setInterval(() => {
        elapsedEl.textContent = Math.floor((Date.now() - startTime) / 1000) + 's';
    }, 1000);
}

function setDone(status) {
    clearInterval(elapsedTimer);
    cursor.style.display = 'none';
    progressBar.style.width = '100%';

    if (status === 'completed') {
        progressBar.className = 'h-1 bg-green-500 transition-all duration-700';
        termStatus.textContent = '✓ Done';
        termStatus.className = 'text-xs font-semibold text-green-400';
        btn.textContent = '✓ Completed — Click to Reload';
        btn.disabled = false;
        btn.onclick = () => location.reload();
    } else {
        progressBar.className = 'h-1 bg-red-500 transition-all duration-700';
        termStatus.textContent = '✗ Failed';
        termStatus.className = 'text-xs font-semibold text-red-400';
        btn.textContent = 'Retry Upgrade';
        btn.disabled = false;
        btn.onclick = null;
    }
}

form.addEventListener('submit', async (e) => {
    e.preventDefault();

    const selected = form.querySelector('input[name="target_version"]:checked');
    if (!selected) { alert('Please select a target version.'); return; }

    btn.disabled = true;
    btn.textContent = 'Starting...';
    termWrap.classList.remove('hidden');
    terminal.innerHTML = '';
    terminal.appendChild(cursor);
    cursor.style.display = 'inline-block';
    lineCount = 0;
    lineCountEl.textContent = '0';
    termStatus.textContent = '● Running';
    termStatus.className = 'text-xs font-semibold text-yellow-400';
    progressBar.className = 'h-1 bg-indigo-500 transition-all duration-700';
    progressBar.style.width = '0%';

    appendOutput('$ composer update laravel/framework --with-all-dependencies\n\n');

    try {
        const res = await fetch("{{ route('laravel-upgrade.upgrade') }}", {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
            },
            body: JSON.stringify({ target_version: selected.value }),
        });

        const data = await res.json();
        if (!data.success) throw new Error(data.message);

        startElapsed();
        progressBar.style.width = '5%';

        // SSE stream — real-time output
        const evtSource = new EventSource(`/laravel-upgrade/stream/${data.upgrade_id}`);
        let progress = 5;

        evtSource.onmessage = (e) => {
            const msg = JSON.parse(e.data);

            if (msg.chunk) {
                appendOutput(msg.chunk);
                progress = Math.min(progress + 0.5, 92);
                progressBar.style.width = progress + '%';
            }

            if (msg.done) {
                evtSource.close();
                setDone(msg.status);
            }
        };

        evtSource.onerror = () => {
            evtSource.close();
            // Fallback: poll status once
            fetch(`/laravel-upgrade/status/${data.upgrade_id}`)
                .then(r => r.json())
                .then(d => setDone(d.status));
        };

    } catch (err) {
        appendOutput('\nError: ' + err.message + '\n');
        setDone('failed');
        btn.disabled = false;
        btn.textContent = 'Start Upgrade';
    }
});
</script>
</body>
</html>
