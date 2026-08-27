<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

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
            word-break: break-word;
        }

        .check-terminal {
            background: #0d1117;
            color: #c9d1d9;
            font-family: 'Courier New', Courier, monospace;
            font-size: 12px;
            line-height: 1.6;
            padding: 16px;
            border-radius: 8px;
            overflow-y: auto;
            max-height: 350px;
            white-space: pre-wrap;
            word-break: break-word;
        }

        .line-ok {
            color: #3fb950;
        }

        .line-err {
            color: #f85149;
        }

        .line-info {
            color: #58a6ff;
        }

        .line-warn {
            color: #d29922;
        }

        .line-dim {
            color: #6e7681;
        }

        .cursor {
            display: inline-block;
            width: 8px;
            height: 14px;
            background: #c9d1d9;
            animation: blink 1s step-end infinite;
            vertical-align: text-bottom;
            margin-left: 2px;
        }

        @keyframes blink {
            50% {
                opacity: 0;
            }
        }

        .spinner {
            width: 18px;
            height: 18px;
            border: 2px solid #e5e7eb;
            border-top-color: #4f46e5;
            border-radius: 50%;
            animation: spin .7s linear infinite;
        }

        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }
    </style>
</head>

<body class="bg-gray-100 min-h-screen">

<div class="max-w-6xl mx-auto py-10 px-4 space-y-6">

    {{-- =====================================================
         HEADER
    ====================================================== --}}

    <div class="bg-white rounded-xl shadow p-6 flex items-center justify-between">

        <div>

            <h1 class="text-2xl font-bold text-gray-900">
                Laravel Version Upgrader
            </h1>

            <p class="text-sm text-gray-500 mt-1">
                Safe Laravel upgrades with compatibility checks
                and Composer dry-run validation
            </p>

        </div>

        <div class="text-right">

            <p class="text-xs text-gray-400 uppercase tracking-wide mb-1">
                Installed Version
            </p>

            <span class="text-3xl font-extrabold text-indigo-600">
                v{{ $currentVersion }}
            </span>

        </div>

    </div>


    {{-- =====================================================
         UPGRADE FORM
    ====================================================== --}}

    <div class="bg-white rounded-xl shadow p-6">

        <h2 class="text-lg font-semibold text-gray-800 mb-4">
            Select Target Version
        </h2>

        <form id="upgradeForm">

            @csrf

            <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">

                @foreach($availableVersions as $version => $label)

                    @php
                        $isCurrent =
                            str_starts_with(
                                $currentVersion,
                                $version . '.'
                            );
                    @endphp

                    <label
                        class="relative {{ $isCurrent
                            ? 'cursor-not-allowed'
                            : 'cursor-pointer' }}"
                    >

                        <input
                            type="radio"
                            name="target_version"
                            value="{{ $version }}"
                            class="sr-only peer"
                            {{ $isCurrent ? 'disabled' : '' }}
                        >

                        <div
                            class="
                                border-2
                                rounded-lg
                                p-4
                                text-center
                                select-none
                                transition

                                {{ $isCurrent
                                    ? 'border-indigo-500 bg-indigo-50'
                                    : 'border-gray-200 hover:border-indigo-400 peer-checked:border-indigo-600 peer-checked:bg-indigo-50'
                                }}
                            "
                        >

                            <p
                                class="
                                    text-lg
                                    font-bold

                                    {{ $isCurrent
                                        ? 'text-indigo-600'
                                        : 'text-gray-800'
                                    }}
                                "
                            >
                                Laravel {{ $version }}
                            </p>

                            <p
                                class="
                                    text-xs
                                    mt-1

                                    {{ $isCurrent
                                        ? 'text-indigo-400'
                                        : 'text-gray-400'
                                    }}
                                "
                            >
                                {{ $isCurrent
                                    ? '✓ Current'
                                    : $label
                                }}
                            </p>

                        </div>

                    </label>

                @endforeach

            </div>


            {{-- Selected version information --}}

            <div
                id="selectedVersionInfo"
                class="hidden mt-5 rounded-lg border border-indigo-100 bg-indigo-50 p-4"
            >

                <div class="flex items-center justify-between">

                    <div>

                        <p class="text-sm font-semibold text-indigo-900">
                            Target Laravel Version
                        </p>

                        <p
                            id="selectedVersionText"
                            class="text-sm text-indigo-700 mt-1"
                        ></p>

                    </div>

                    <div class="text-right">

                        <p class="text-xs text-indigo-500">
                            PHP Requirement
                        </p>

                        <p
                            id="phpRequirement"
                            class="font-semibold text-indigo-800"
                        >
                            -
                        </p>

                    </div>

                </div>

            </div>


            {{-- =================================================
                 PRE-UPGRADE CHECKS
            ================================================== --}}

            <div
                id="checksSection"
                class="hidden mt-6 space-y-4"
            >

                <div class="flex items-center justify-between">

                    <h3 class="text-base font-semibold text-gray-800">
                        Pre-Upgrade Validation
                    </h3>

                    <span
                        id="checkOverallBadge"
                        class="px-3 py-1 text-xs font-semibold rounded-full bg-gray-100 text-gray-600"
                    >
                        Not checked
                    </span>

                </div>


                {{-- Environment checks --}}

                <div class="border rounded-lg overflow-hidden">

                    <div class="bg-gray-50 px-4 py-3 flex items-center justify-between">

                        <div>

                            <p class="font-semibold text-gray-800">
                                🔍 Environment Compatibility
                            </p>

                            <p class="text-xs text-gray-500">
                                PHP, extensions and Composer
                            </p>

                        </div>

                        <span
                            id="environmentBadge"
                            class="text-xs font-semibold text-gray-500"
                        >
                            Waiting
                        </span>

                    </div>

                    <div
                        id="environmentChecks"
                        class="divide-y"
                    ></div>

                </div>


                {{-- Dependency report --}}

                <div class="border rounded-lg overflow-hidden">

                    <div class="bg-gray-50 px-4 py-3 flex items-center justify-between">

                        <div>

                            <p class="font-semibold text-gray-800">
                                🧩 Dependency Compatibility
                            </p>

                            <p class="text-xs text-gray-500">
                                Runtime and development packages
                            </p>

                        </div>

                        <span
                            id="dependencyBadge"
                            class="text-xs font-semibold text-gray-500"
                        >
                            Waiting
                        </span>

                    </div>

                    <div
                        id="dependencyChecks"
                        class="divide-y max-h-80 overflow-y-auto"
                    ></div>

                </div>

            </div>


            {{-- =================================================
                 DRY RUN
            ================================================== --}}

            <div
                id="dryRunSection"
                class="hidden mt-6"
            >

                <div class="flex items-center justify-between mb-3">

                    <div>

                        <h3 class="text-base font-semibold text-gray-800">
                            🧪 Composer Dependency Dry-Run
                        </h3>

                        <p class="text-xs text-gray-500 mt-1">
                            Composer will simulate the upgrade without
                            changing your final dependency files.
                        </p>

                    </div>

                    <span
                        id="dryRunBadge"
                        class="px-3 py-1 text-xs font-semibold rounded-full bg-gray-100 text-gray-600"
                    >
                        Not started
                    </span>

                </div>


                <div class="bg-gray-800 rounded-t-lg px-4 py-2 flex items-center justify-between">

                    <span class="text-xs text-gray-400 font-mono">
                        composer update laravel/framework
                        --with-all-dependencies --dry-run
                    </span>

                    <span
                        id="dryRunSpinner"
                        class="hidden spinner"
                    ></span>

                </div>

                <div
                    id="dryRunTerminal"
                    class="check-terminal rounded-t-none"
                >
                    Select a Laravel version and run validation.
                </div>

            </div>


            {{-- =================================================
                 ACTION BUTTONS
            ================================================== --}}

            <div class="mt-6 grid grid-cols-1 sm:grid-cols-2 gap-3">

                <button
                    type="button"
                    id="checkBtn"
                    disabled
                    class="
                        border
                        border-indigo-600
                        text-indigo-600
                        font-semibold
                        py-3
                        rounded-lg
                        hover:bg-indigo-50
                        transition
                        disabled:opacity-50
                        disabled:cursor-not-allowed
                    "
                >
                    🔍 Check Compatibility
                </button>

                <button
                    type="button"
                    id="dryRunBtn"
                    disabled
                    class="
                        border
                        border-purple-600
                        text-purple-600
                        font-semibold
                        py-3
                        rounded-lg
                        hover:bg-purple-50
                        transition
                        disabled:opacity-50
                        disabled:cursor-not-allowed
                    "
                >
                    🧪 Run Composer Dry-Run
                </button>

            </div>


            <button
                type="submit"
                id="upgradeBtn"
                disabled
                class="
                    w-full
                    mt-3
                    bg-indigo-600
                    text-white
                    font-semibold
                    py-3
                    rounded-lg
                    hover:bg-indigo-700
                    transition
                    disabled:opacity-50
                    disabled:cursor-not-allowed
                "
            >
                🚀 Start Upgrade
            </button>

        </form>


        {{-- =====================================================
             REAL UPGRADE TERMINAL
        ====================================================== --}}

        <div
            id="terminalWrap"
            class="mt-6 hidden"
        >

            <div
                class="flex items-center justify-between bg-gray-800 rounded-t-lg px-4 py-2"
            >

                <div class="flex gap-1.5">

                    <span class="w-3 h-3 rounded-full bg-red-500"></span>

                    <span class="w-3 h-3 rounded-full bg-yellow-400"></span>

                    <span class="w-3 h-3 rounded-full bg-green-500"></span>

                </div>

                <span
                    id="termTitle"
                    class="text-xs text-gray-400 font-mono"
                >
                    composer update laravel/framework
                </span>

                <span
                    id="termStatus"
                    class="text-xs font-semibold text-yellow-400"
                >
                    ● Running
                </span>

            </div>


            <div class="bg-gray-700 h-1">

                <div
                    id="progressBar"
                    class="h-1 bg-indigo-500 transition-all duration-700"
                    style="width:0%"
                ></div>

            </div>


            <div
                id="terminal"
                class="terminal rounded-b-lg"
            >
                <span
                    id="cursor"
                    class="cursor"
                ></span>
            </div>


            <div
                class="flex gap-4 mt-2 text-xs text-gray-400 font-mono px-1"
            >

                <span>
                    Lines:
                    <span id="lineCount">0</span>
                </span>

                <span>
                    Elapsed:
                    <span id="elapsed">0s</span>
                </span>

            </div>

        </div>

    </div>


    {{-- =====================================================
         RECENT UPGRADES
    ====================================================== --}}

    <div class="bg-white rounded-xl shadow p-6">

        <div class="flex items-center justify-between mb-4">

            <div>

                <h2 class="text-lg font-semibold text-gray-800">
                    Recent Upgrades
                </h2>

                <p class="text-xs text-gray-500 mt-1">
                    Upgrade history and Composer output
                </p>

            </div>

            <span class="text-xs text-gray-400">
                Last 10
            </span>

        </div>


        @forelse($recentUpgrades as $upgrade)

            <div
                class="border border-gray-100 rounded-lg p-4 mb-3 last:mb-0"
            >

                <div
                    class="flex items-center justify-between flex-wrap gap-2"
                >

                    <div class="flex items-center gap-2 flex-wrap">

                        <code
                            class="text-sm bg-gray-100 text-gray-700 px-2 py-0.5 rounded"
                        >
                            v{{ $upgrade->current_version }}
                        </code>

                        <span class="text-gray-400 text-sm">
                            →
                        </span>

                        <code
                            class="text-sm bg-indigo-50 text-indigo-700 px-2 py-0.5 rounded"
                        >
                            v{{ $upgrade->target_version }}
                        </code>


                        @if($upgrade->status === 'completed')

                            <span
                                class="px-2 py-0.5 text-xs font-semibold rounded-full bg-green-100 text-green-700"
                            >
                                ✓ Completed
                            </span>

                        @elseif($upgrade->status === 'failed')

                            <span
                                class="px-2 py-0.5 text-xs font-semibold rounded-full bg-red-100 text-red-700"
                            >
                                ✗ Failed
                            </span>

                        @elseif($upgrade->status === 'running')

                            <span
                                class="px-2 py-0.5 text-xs font-semibold rounded-full bg-yellow-100 text-yellow-700 animate-pulse"
                            >
                                ⟳ Running
                            </span>

                        @else

                            <span
                                class="px-2 py-0.5 text-xs font-semibold rounded-full bg-gray-100 text-gray-600"
                            >
                                Pending
                            </span>

                        @endif

                    </div>


                    <div class="text-right text-xs text-gray-400">

                        <div>
                            {{ $upgrade->created_at->diffForHumans() }}
                        </div>

                        @if($upgrade->completed_at && $upgrade->started_at)

                            <div>
                                {{ $upgrade->started_at->diffInSeconds($upgrade->completed_at) }}s duration
                            </div>

                        @endif

                    </div>

                </div>


                @if($upgrade->output)

                    <details class="mt-3">

                        <summary
                            class="
                                text-xs
                                cursor-pointer
                                hover:underline

                                {{ $upgrade->status === 'failed'
                                    ? 'text-red-500'
                                    : 'text-gray-500'
                                }}
                            "
                        >
                            Show output
                        </summary>

                        <div
                            class="mt-2 terminal rounded-lg text-xs max-h-48"
                        >
                            {{ $upgrade->output }}
                        </div>

                    </details>

                @endif

            </div>

        @empty

            <p
                class="text-sm text-gray-400 text-center py-8"
            >
                No upgrade history yet.
            </p>

        @endforelse

    </div>

</div>


<script>

const form =
    document.getElementById('upgradeForm');

const checkBtn =
    document.getElementById('checkBtn');

const dryRunBtn =
    document.getElementById('dryRunBtn');

const upgradeBtn =
    document.getElementById('upgradeBtn');

const checksSection =
    document.getElementById('checksSection');

const dryRunSection =
    document.getElementById('dryRunSection');

const environmentChecks =
    document.getElementById('environmentChecks');

const dependencyChecks =
    document.getElementById('dependencyChecks');

const environmentBadge =
    document.getElementById('environmentBadge');

const dependencyBadge =
    document.getElementById('dependencyBadge');

const checkOverallBadge =
    document.getElementById('checkOverallBadge');

const dryRunBadge =
    document.getElementById('dryRunBadge');

const dryRunTerminal =
    document.getElementById('dryRunTerminal');

const dryRunSpinner =
    document.getElementById('dryRunSpinner');

const selectedVersionInfo =
    document.getElementById('selectedVersionInfo');

const selectedVersionText =
    document.getElementById('selectedVersionText');

const phpRequirement =
    document.getElementById('phpRequirement');


/* =========================================================
   STATE
========================================================= */

let selectedVersion = null;

let compatibilityPassed = false;

let dryRunPassed = false;

let startTime;

let elapsedTimer;

let lineCount = 0;


/* =========================================================
   VERSION SELECTION
========================================================= */

document
    .querySelectorAll('input[name="target_version"]')
    .forEach(input => {

        input.addEventListener('change', () => {

            selectedVersion =
                input.value;

            selectedVersionInfo
                .classList
                .remove('hidden');

            selectedVersionText.textContent =
                `Laravel ${selectedVersion}.x`;

            const phpRequirements = {
                12: 'PHP 8.2+',
                11: 'PHP 8.2+',
                10: 'PHP 8.1+',
                9: 'PHP 8.0.2+'
            };

            phpRequirement.textContent =
                phpRequirements[selectedVersion] ||
                'See Composer requirements';

            checksSection
                .classList
                .add('hidden');

            dryRunSection
                .classList
                .add('hidden');

            compatibilityPassed = false;

            dryRunPassed = false;

            checkBtn.disabled = false;

            dryRunBtn.disabled = true;

            upgradeBtn.disabled = true;

            checkOverallBadge.textContent =
                'Not checked';

            checkOverallBadge.className =
                'px-3 py-1 text-xs font-semibold rounded-full bg-gray-100 text-gray-600';

            environmentBadge.textContent =
                'Waiting';

            dependencyBadge.textContent =
                'Waiting';

            dryRunBadge.textContent =
                'Not started';

        });

    });


/* =========================================================
   STATUS BADGE
========================================================= */

function statusBadge(status) {

    if (status === 'pass') {

        return `
            <span class="
                px-2
                py-1
                rounded-full
                text-xs
                font-semibold
                bg-green-100
                text-green-700
            ">
                ✓ Pass
            </span>
        `;

    }

    if (status === 'error') {

        return `
            <span class="
                px-2
                py-1
                rounded-full
                text-xs
                font-semibold
                bg-red-100
                text-red-700
            ">
                ✗ Error
            </span>
        `;

    }

    if (status === 'warning') {

        return `
            <span class="
                px-2
                py-1
                rounded-full
                text-xs
                font-semibold
                bg-yellow-100
                text-yellow-700
            ">
                ⚠ Warning
            </span>
        `;

    }

    if (status === 'target') {

        return `
            <span class="
                px-2
                py-1
                rounded-full
                text-xs
                font-semibold
                bg-indigo-100
                text-indigo-700
            ">
                → Target
            </span>
        `;

    }

    return `
        <span class="
            px-2
            py-1
            rounded-full
            text-xs
            font-semibold
            bg-gray-100
            text-gray-600
        ">
            Review
        </span>
    `;
}


/* =========================================================
   COMPATIBILITY CHECK
========================================================= */

checkBtn.addEventListener('click', async () => {

    if (!selectedVersion) {

        alert(
            'Please select a target Laravel version.'
        );

        return;
    }

    checkBtn.disabled = true;

    checkBtn.textContent =
        'Checking...';

    checksSection
        .classList
        .remove('hidden');

    environmentChecks.innerHTML = `
        <div class="p-4 text-sm text-gray-500">
            Checking PHP environment...
        </div>
    `;

    dependencyChecks.innerHTML = `
        <div class="p-4 text-sm text-gray-500">
            Reading composer.json...
        </div>
    `;

    try {

        const response =
            await fetch(
                "{{ route('laravel-upgrade.compatibility') }}",
                {
                    method: 'POST',

                    headers: {
                        'Content-Type':
                            'application/json',

                        'X-CSRF-TOKEN':
                            '{{ csrf_token() }}',

                        'Accept':
                            'application/json'
                    },

                    body: JSON.stringify({
                        target_version:
                            selectedVersion
                    })
                }
            );

        const data =
            await response.json();

        if (!response.ok) {

            throw new Error(
                data.message ||
                'Compatibility check failed.'
            );
        }


        /* -----------------------------------------------
           ENVIRONMENT
        ------------------------------------------------ */

        environmentChecks.innerHTML = '';

        data.environment.checks
            .forEach(check => {

                const row =
                    document.createElement('div');

                row.className =
                    'p-4 flex items-center justify-between gap-4';

                row.innerHTML = `

                    <div class="min-w-0">

                        <p class="text-sm font-semibold text-gray-800">
                            ${escapeHtml(check.name)}
                        </p>

                        <p class="text-xs text-gray-500 mt-1">
                            ${escapeHtml(check.message)}
                        </p>

                    </div>

                    <div class="flex-shrink-0">

                        ${statusBadge(check.status)}

                    </div>

                `;

                environmentChecks.appendChild(row);

            });


        /* -----------------------------------------------
           DEPENDENCIES
        ------------------------------------------------ */

        dependencyChecks.innerHTML = '';

        data.dependencies.dependencies
            .forEach(dependency => {

                const row =
                    document.createElement('div');

                row.className =
                    'p-4 flex items-center justify-between gap-4';

                row.innerHTML = `

                    <div class="min-w-0">

                        <div class="flex items-center gap-2">

                            <code class="
                                text-xs
                                bg-gray-100
                                px-2
                                py-1
                                rounded
                            ">
                                ${escapeHtml(dependency.name)}
                            </code>

                            <span class="text-xs text-gray-400">
                                ${escapeHtml(dependency.constraint)}
                            </span>

                        </div>

                        <p class="text-xs text-gray-500 mt-2">
                            ${escapeHtml(dependency.message)}
                        </p>

                    </div>

                    <div class="flex-shrink-0">

                        ${statusBadge(dependency.status)}

                    </div>

                `;

                dependencyChecks.appendChild(row);

            });


        /* -----------------------------------------------
           BADGES
        ------------------------------------------------ */

        if (data.environment.success) {

            environmentBadge.textContent =
                '✓ Compatible';

            environmentBadge.className =
                'text-xs font-semibold text-green-600';

        } else {

            environmentBadge.textContent =
                '✗ Issues Found';

            environmentBadge.className =
                'text-xs font-semibold text-red-600';

        }


        dependencyBadge.textContent =
            '✓ Report Generated';

        dependencyBadge.className =
            'text-xs font-semibold text-green-600';


        compatibilityPassed =
            data.environment.success;


        if (compatibilityPassed) {

            checkOverallBadge.textContent =
                '✓ Environment Ready';

            checkOverallBadge.className =
                'px-3 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-700';

            dryRunBtn.disabled = false;

        } else {

            checkOverallBadge.textContent =
                '✗ Fix Issues First';

            checkOverallBadge.className =
                'px-3 py-1 text-xs font-semibold rounded-full bg-red-100 text-red-700';

            dryRunBtn.disabled = true;

        }

    } catch (error) {

        environmentChecks.innerHTML = `
            <div class="p-4 text-sm text-red-600">
                ✗ ${escapeHtml(error.message)}
            </div>
        `;

        compatibilityPassed = false;

        dryRunBtn.disabled = true;

    } finally {

        checkBtn.disabled = false;

        checkBtn.textContent =
            '🔍 Check Compatibility';

    }

});


/* =========================================================
   DRY RUN
========================================================= */

dryRunBtn.addEventListener('click', async () => {

    if (!selectedVersion) {
        return;
    }

    if (!compatibilityPassed) {

        alert(
            'Please pass the compatibility check first.'
        );

        return;
    }

    dryRunBtn.disabled = true;

    upgradeBtn.disabled = true;

    dryRunSection
        .classList
        .remove('hidden');

    dryRunSpinner
        .classList
        .remove('hidden');

    dryRunBadge.textContent =
        'Running...';

    dryRunBadge.className =
        'px-3 py-1 text-xs font-semibold rounded-full bg-yellow-100 text-yellow-700';

    dryRunTerminal.textContent =
        '$ composer update laravel/framework --with-all-dependencies --dry-run\n\n';

    dryRunTerminal.textContent +=
        'Resolving dependencies...\n\n';


    try {

        const response =
            await fetch(
                "{{ route('laravel-upgrade.dry-run') }}",
                {
                    method: 'POST',

                    headers: {
                        'Content-Type':
                            'application/json',

                        'X-CSRF-TOKEN':
                            '{{ csrf_token() }}',

                        'Accept':
                            'application/json'
                    },

                    body: JSON.stringify({
                        target_version:
                            selectedVersion
                    })
                }
            );

        const data =
            await response.json();


        if (data.output) {

            dryRunTerminal.textContent +=
                data.output;
        }


        if (!response.ok || !data.success) {

            dryRunPassed = false;

            dryRunBadge.textContent =
                '✗ Conflicts Found';

            dryRunBadge.className =
                'px-3 py-1 text-xs font-semibold rounded-full bg-red-100 text-red-700';

            upgradeBtn.disabled = true;

            return;
        }


        dryRunPassed = true;

        dryRunBadge.textContent =
            '✓ Passed';

        dryRunBadge.className =
            'px-3 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-700';

        dryRunTerminal.textContent +=
            '\n\n✓ Composer dry-run completed successfully.\n';

        dryRunTerminal.textContent +=
            '✓ No dependency resolution errors were detected.\n';

        upgradeBtn.disabled = false;

    } catch (error) {

        dryRunPassed = false;

        dryRunBadge.textContent =
            '✗ Failed';

        dryRunBadge.className =
            'px-3 py-1 text-xs font-semibold rounded-full bg-red-100 text-red-700';

        dryRunTerminal.textContent +=
            '\n\n✗ ' +
            error.message;

        upgradeBtn.disabled = true;

    } finally {

        dryRunBtn.disabled = false;

        dryRunSpinner
            .classList
            .add('hidden');

    }

});


/* =========================================================
   REAL UPGRADE
========================================================= */

const terminalWrap =
    document.getElementById('terminalWrap');

const terminal =
    document.getElementById('terminal');

const cursor =
    document.getElementById('cursor');

const termStatus =
    document.getElementById('termStatus');

const progressBar =
    document.getElementById('progressBar');

const lineCountEl =
    document.getElementById('lineCount');

const elapsedEl =
    document.getElementById('elapsed');


function stripAnsi(str) {

    return str
        .replace(
            /\x1B(?:[@-Z\\-_]|\[[0-?]*[ -/]*[@-~]|\][^\x07]*(?:\x07|\x1B\\))/g,
            ''
        );

}


function colorizeLine(line) {

    const span =
        document.createElement('span');

    if (
        /Installing|Upgrading|Downgrading/i
            .test(line)
    ) {

        span.className =
            'line-info';

    } else if (
        /error|failed|exception/i
            .test(line)
    ) {

        span.className =
            'line-err';

    } else if (
        /warning|warn/i
            .test(line)
    ) {

        span.className =
            'line-warn';

    } else if (
        /done|success|completed|installed|upgraded/i
            .test(line)
    ) {

        span.className =
            'line-ok';

    } else if (
        /^Loading|^Updating|^Writing|^Generating|^Package operations/i
            .test(line.trim())
    ) {

        span.className =
            'line-dim';

    }

    span.textContent =
        line;

    return span;

}


function appendOutput(text) {

    const clean =
        stripAnsi(text);

    const lines =
        clean.split('\n');

    lines.forEach((line, i) => {

        if (i < lines.length - 1) {

            terminal.insertBefore(
                colorizeLine(line),
                cursor
            );

            terminal.insertBefore(
                document.createTextNode('\n'),
                cursor
            );

            lineCount++;

        } else if (line) {

            terminal.insertBefore(
                colorizeLine(line),
                cursor
            );

        }

    });

    lineCountEl.textContent =
        lineCount;

    terminal.scrollTop =
        terminal.scrollHeight;

}


function startElapsed() {

    startTime =
        Date.now();

    elapsedTimer =
        setInterval(() => {

            elapsedEl.textContent =
                Math.floor(
                    (Date.now() - startTime) / 1000
                ) + 's';

        }, 1000);

}


function setDone(status) {

    clearInterval(
        elapsedTimer
    );

    cursor.style.display =
        'none';

    progressBar.style.width =
        '100%';


    if (status === 'completed') {

        progressBar.className =
            'h-1 bg-green-500 transition-all duration-700';

        termStatus.textContent =
            '✓ Done';

        termStatus.className =
            'text-xs font-semibold text-green-400';

        upgradeBtn.textContent =
            '✓ Completed — Click to Reload';

        upgradeBtn.disabled =
            false;

        upgradeBtn.onclick =
            () => location.reload();

    } else {

        progressBar.className =
            'h-1 bg-red-500 transition-all duration-700';

        termStatus.textContent =
            '✗ Failed';

        termStatus.className =
            'text-xs font-semibold text-red-400';

        upgradeBtn.textContent =
            'Retry Upgrade';

        upgradeBtn.disabled =
            false;

    }

}


form.addEventListener(
    'submit',
    async (e) => {

        e.preventDefault();

        if (!selectedVersion) {

            alert(
                'Please select a target version.'
            );

            return;

        }

        if (!compatibilityPassed) {

            alert(
                'Please complete the compatibility check first.'
            );

            return;

        }

        if (!dryRunPassed) {

            alert(
                'Please run and pass Composer dry-run first.'
            );

            return;

        }


        upgradeBtn.disabled =
            true;

        checkBtn.disabled =
            true;

        dryRunBtn.disabled =
            true;

        upgradeBtn.textContent =
            'Starting...';


        terminalWrap
            .classList
            .remove('hidden');

        terminal.innerHTML = '';

        terminal.appendChild(
            cursor
        );

        cursor.style.display =
            'inline-block';

        lineCount =
            0;

        lineCountEl.textContent =
            '0';

        termStatus.textContent =
            '● Running';

        termStatus.className =
            'text-xs font-semibold text-yellow-400';

        progressBar.className =
            'h-1 bg-indigo-500 transition-all duration-700';

        progressBar.style.width =
            '0%';


        appendOutput(
            '$ composer update laravel/framework --with-all-dependencies\n\n'
        );


        try {

            const res =
                await fetch(
                    "{{ route('laravel-upgrade.upgrade') }}",
                    {
                        method: 'POST',

                        headers: {
                            'Content-Type':
                                'application/json',

                            'X-CSRF-TOKEN':
                                '{{ csrf_token() }}',

                            'Accept':
                                'application/json'
                        },

                        body: JSON.stringify({
                            target_version:
                                selectedVersion
                        })
                    }
                );


            const data =
                await res.json();


            if (!res.ok || !data.success) {

                throw new Error(
                    data.message ||
                    'Unable to start upgrade.'
                );

            }


            startElapsed();

            progressBar.style.width =
                '5%';


            const evtSource =
                new EventSource(
                    `/laravel-upgrade/stream/${data.upgrade_id}`
                );


            let progress = 5;


            evtSource.onmessage =
                (event) => {

                    const msg =
                        JSON.parse(
                            event.data
                        );


                    if (msg.chunk) {

                        appendOutput(
                            msg.chunk
                        );

                        progress =
                            Math.min(
                                progress + 0.5,
                                92
                            );

                        progressBar.style.width =
                            progress + '%';

                    }


                    if (msg.done) {

                        evtSource.close();

                        setDone(
                            msg.status
                        );

                    }

                };


            evtSource.onerror =
                () => {

                    evtSource.close();

                    fetch(
                        `/laravel-upgrade/status/${data.upgrade_id}`
                    )
                        .then(
                            response =>
                                response.json()
                        )
                        .then(
                            result =>
                                setDone(
                                    result.status
                                )
                        )
                        .catch(() => {
                            setDone('failed');
                        });

                };


        } catch (error) {

            appendOutput(
                '\n✗ Error: ' +
                error.message +
                '\n'
            );

            setDone(
                'failed'
            );

            upgradeBtn.disabled =
                false;

            upgradeBtn.textContent =
                'Start Upgrade';

        }

    }
);


/* =========================================================
   HTML ESCAPE
========================================================= */

function escapeHtml(value) {

    return String(value)
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');

}

</script>

</body>

</html> 