{{-- resources/views/livewire/head/dashboard.blade.php --}}

<style>
/* ===================== PRINT ONLY ===================== */
@media print {
    nav, aside, header, footer, .navbar, .sidebar, .print\:hidden { display:none !important; visibility:hidden !important; }
    @page { margin-top:1in; margin-bottom:2in; margin-left:1in; margin-right:1in; }

    html, body {
        margin:0 !important; padding:0 !important; background:#fff !important;
        -webkit-print-color-adjust:exact; print-color-adjust:exact;
        height:auto !important; overflow:visible !important;
    }

    .container, .max-w-7xl, .mx-auto, .sm\:px-6, .lg\:px-8,
    .print-root, .page, .p-6, .py-6, .py-8, .py-12, .px-6, .px-8,
    [class*="shadow"], [class*="ring-"], [class*="border-"] {
        background:#fff !important; border:none !important; box-shadow:none !important;
        outline:none !important; margin:0 !important; padding:0 !important;
    }

    .page { page-break-after:always; display:block; width:100%; margin:0 auto; padding:0; }
    .page-inner { width:100%; margin:0 auto; padding:0; page-break-inside:avoid; box-sizing:border-box; }

    .header-image { width:90%; margin:0 auto 4px; display:flex; justify-content:center; align-items:center; }
    .header-image img { max-height:70px; height:auto; display:block; margin:0 auto; }

    .meta-info { width:90%; margin:4px auto; font-family:ui-monospace, Menlo, Monaco, Consolas, "Courier New", monospace; font-size:13px; }
    .meta-row { display:flex; justify-content:space-between; margin-bottom:2px; width:100%; }
    .meta-row .left  { padding-left:26mm; }
    .meta-row .right { padding-right:26mm; text-align:right; }

    .h-title { font-size:22px; font-weight:700; text-align:center; margin:4px 0 8px; }

    .tbl { width:90% !important; margin:0 auto; border-collapse:collapse !important; table-layout:fixed !important; background:#fff !important; }
    .tbl th, .tbl td { border:1px solid #1f2937 !important; padding:4px 6px !important; font-size:11px !important; line-height:1.15 !important; height:22px !important; vertical-align:middle !important; }
    .tbl th { background:#fff !important; font-weight:700; } /* ✅ SAME COLOR as data */

    .all-term { width:90%; margin:6px auto 4px; font-family:ui-monospace, Menlo, Monaco, Consolas, "Courier New", monospace; font-weight:700; font-size:12px; }

    .fixed, .sticky, [class*="sticky"], [class*="fixed"] { position:static !important; }
    * { overflow:visible !important; }
}

/* ===================== SCREEN STYLES ===================== */
/* ✅ Keep dashboard clean, and mimic editor print look */
body { background:#f5f6f8; }

.print-root { background:#fbfaf8; padding:18px; }

/* ✅ FIX HEADER ARRANGEMENT: always centered */
.header-image {
    width:90%;
    margin:10px auto 6px;
    display:flex;
    justify-content:center;
    align-items:center;
}
.header-image img {
    height:80px;
    width:auto;
    object-fit:contain;
    display:block;
}

/* meta info box */
.meta-info {
    width:90%;
    margin:6px auto;
    font-family:ui-monospace, Menlo, Monaco, Consolas, "Courier New", monospace;
    font-size:13px;
}
.meta-row { display:flex; justify-content:space-between; margin-bottom:2px; width:100%; }
/* ✅ screen: no big inch padding (mao naguba ug alignment) */
.meta-row .left  { padding-left:0; }
.meta-row .right { padding-right:0; text-align:right; }

.h-title { font-size:22px; font-weight:700; text-align:center; margin:8px 0 10px; }

/* ✅ TABLE: fixed widths + SAME COLOR header + body */
.tbl {
    width:90%;
    margin:0 auto;
    border-collapse:collapse;
    table-layout:fixed;
    background:#fff;
}
.tbl th, .tbl td {
    border:1px solid #1f2937;
    padding:6px 8px;
    font-size:13px;
    line-height:1.15;
    height:34px;
    vertical-align:middle;
    overflow:hidden;
    text-overflow:ellipsis;
    white-space:nowrap;
    background:#fff;              /* ✅ SAME COLOR FOR ALL CELLS */
}
.tbl th { font-weight:700; background:#fff; } /* ✅ SAME COLOR as data */

/* allow descriptive title wrap */
.tbl td:nth-child(2) { white-space:normal; line-height:1.25; }

.all-term {
    width:90%;
    margin:6px auto 4px;
    font-family:ui-monospace, Menlo, Monaco, Consolas, "Courier New", monospace;
    font-weight:700;
    font-size:12px;
}

/* INC highlight */
.inc { color:#dc2626; font-weight:700; }

/* optional: add spacing between sections */
.section-block { padding-bottom:24px; }

/* ===================== DASHBOARD-ONLY CHANGES (SCREEN ONLY) ===================== */
/* ✅ IMPORTANT: wrap in @media screen so PRINT is never affected */
@media screen {
    /* ✅ First section only shows header/meta on dashboard */
    .page.is-not-first .header-image,
    .page.is-not-first .meta-info {
        display:none;
    }

    /* ✅ tighter spacing for non-first sections */
    .page.is-not-first .h-title {
        margin-top:4px;
    }

    .page.is-not-first .page-inner {
        padding-top:6px;
    }
}
</style>

@php
    // term helpers (keep your existing behavior)
    $terms = \App\Models\AcademicYear::orderByDesc('id')->get();
    $activeId = \App\Models\AcademicYear::where('is_active', 1)->value('id');
    $currentTerm = $terms->firstWhere('id', (int)($academicId ?? 0));
@endphp

<div class="print-root space-y-6">

    {{-- FILTER BAR (screen only) --}}
    <form method="GET" action="{{ url()->current() }}" class="flex flex-wrap items-end gap-3 print:hidden">
        <div class="flex items-center gap-2">
            <input type="checkbox" name="history" value="1" @checked($showHistory ?? false)
                   id="history-toggle" class="border rounded">
            <label for="history-toggle" class="text-sm">History</label>
        </div>

        <div>
            <label class="block text-xs text-gray-600 mb-1">Academic Term</label>
            <select name="academic" class="border rounded px-3 py-2 w-64">
                @foreach($terms as $a)
                    @php
                        $sem = strtolower((string)$a->semester);
                        $semLabel = in_array($sem,['1','1st','first']) ? '1st Sem'
                                  : (in_array($sem,['2','2nd','second']) ? '2nd Sem'
                                  : (str_contains($sem,'mid') || $sem==='3' ? 'Midyear' : ucwords($a->semester)));
                    @endphp
                    <option value="{{ $a->id }}" @selected((int)($academicId ?? 0) === (int)$a->id)>
                        {{ $a->school_year }} — {{ $semLabel }} @if((int)$a->id === (int)$activeId) (Active) @endif
                    </option>
                @endforeach
            </select>
        </div>

        <div>
            <label class="block text-xs text-gray-600 mb-1">Section</label>
            <select name="section" class="border rounded px-3 py-2 w-64">
                <option value="">All sections</option>
                @foreach($sectionOptions as $opt)
                    <option value="{{ $opt['value'] }}" @selected((int)($sectionId ?? 0) === (int)$opt['value'])>
                        {{ $opt['label'] }}
                    </option>
                @endforeach
            </select>
        </div>

        <button class="bg-blue-600 text-white rounded px-4 py-2">Apply</button>
        <button type="button" onclick="window.print()" class="bg-gray-700 text-white rounded px-4 py-2">Print</button>
    </form>

    {{-- SHEETS --}}
    @forelse($sheets as $sheet)
        @php
            $semRaw  = $sheet['semester'] ?? '';
            $semLabel = $semRaw ?: '—';

            $yearRaw = $sheet['year_level'] ?? '';
            $yearLabel = $yearRaw ?: '—';
        @endphp

        {{-- ✅ marker classes (dashboard only); print is NOT affected due to @media screen --}}
        <div class="page section-block {{ $loop->first ? 'is-first' : 'is-not-first' }}">
            <div class="page-inner">

                {{-- HEADER IMAGE --}}
                <div class="header-image">
                    <img src="{{ asset('images/sfxc_header.png') }}" alt="SFXC Header">
                </div>

                {{-- META INFO --}}
                <div class="meta-info">
                    <div class="meta-row">
                        <div class="left">School Year : <strong>{{ $sheet['school_year'] ?? '—' }}</strong></div>
                        <div class="right">Semester : <strong>{{ $semLabel }}</strong></div>
                    </div>
                    <div class="meta-row">
                        <div class="left">Program Name : <strong>{{ $sheet['course'] ?? '—' }}</strong></div>

                        {{-- ✅ DASHBOARD: ALL | PRINT: real year level --}}
                        <div class="right">
                            Year Level :
                            <strong>
                                <span class="print:hidden">{{ !empty($sectionId) ? $yearLabel : 'ALL' }}</span>
                                <span class="hidden print:inline">{{ $yearLabel }}</span>

                            </strong>
                        </div>
                    </div>
                </div>

                {{-- SECTION TITLE --}}
                <div class="h-title">{{ $sheet['section'] ?? 'SECTION' }}</div>

                {{-- HEADER TABLE --}}
                <table class="tbl">
                    <colgroup>
                        <col style="width:110px">
                        <col style="width:360px">
                        <col style="width:70px">
                        <col style="width:120px">
                        <col style="width:120px">
                        <col style="width:90px">
                        <col style="width:90px">
                        <col style="width:160px">
                    </colgroup>
                    <thead>
                        <tr>
                            <th>Course Code</th>
                            <th>Descriptive Title</th>
                            <th>Units</th>
                            <th>Start Time</th>
                            <th>End Time</th>
                            <th>Days</th>
                            <th>Room</th>
                            <th>Instructor</th>
                        </tr>
                    </thead>
                </table>

                <div class="all-term">ALL Term</div>

                {{-- BODY TABLE --}}
                <table class="tbl">
                    <colgroup>
                        <col style="width:110px">
                        <col style="width:360px">
                        <col style="width:70px">
                        <col style="width:120px">
                        <col style="width:120px">
                        <col style="width:90px">
                        <col style="width:90px">
                        <col style="width:160px">
                    </colgroup>
                    <tbody>
                        @forelse($sheet['rows'] as $r)
                            @php
                                $inst = (string)($r['inst'] ?? '');
                                $isInc = strtolower(trim($inst)) === 'inc';
                            @endphp
                            <tr>
                                <td>{{ $r['code'] ?? '—' }}</td>
                                <td>{{ $r['title'] ?? '—' }}</td>
                                <td style="text-align:center;">{{ $r['units'] ?? '—' }}</td>
                                <td>{{ $r['st'] ?? '—' }}</td>
                                <td>{{ $r['et'] ?? '—' }}</td>
                                <td>{{ $r['days'] ?? '—' }}</td>
                                <td>{{ $r['room'] ?? '—' }}</td>
                                <td class="{{ $isInc ? 'inc' : '' }}">{{ $r['inst'] ?? '—' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" style="text-align:center;color:#6b7280;">No meetings found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>

            </div>
        </div>
    @empty
        <div class="text-gray-500">No schedules found.</div>
    @endforelse

</div>
