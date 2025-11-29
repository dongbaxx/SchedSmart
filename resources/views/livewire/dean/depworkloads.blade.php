{{-- resources/views/livewire/dean/depworkloads.blade.php --}}

{{-- ===================== PRINT & SCREEN STYLES ===================== --}}
<style>
@media print {
  nav, aside, header, footer, .navbar, .sidebar, .print\:hidden {
    display:none !important;
    visibility:hidden !important;
  }

  @page { margin: 1in 1in 2in 1in; }

  html, body {
    margin:0!important;
    padding:0!important;
    background:#ffffff!important;
    height:auto!important;
    -webkit-print-color-adjust:exact;
    print-color-adjust:exact;
    overflow:visible!important;
  }

  .container, .max-w-7xl, .mx-auto, .sm\:px-6, .lg\:px-8,
  .print-root, .page, .p-6, .py-6, .py-8, .py-12, .px-6, .px-8,
  [class*="shadow"], [class*="ring-"], [class*="border-"] {
    background:#ffffff!important;
    border:none!important;
    box-shadow:none!important;
    outline:none!important;
    margin:0!important;
    padding:0!important;
  }

  .page {
    page-break-after:always;
    display:block;
    width:100%;
    height:auto!important;
    margin:0 auto;
    padding:0;
  }

  .page-inner {
    display:block;
    width:100%;
    height:auto;
    margin:0 auto;
    padding:0;
    background:#ffffff!important;
    box-sizing:border-box;
    page-break-inside:avoid;
  }

  .header-image { margin-bottom:4px; }
  .header-image img {
    max-height:70px;
    height:auto;
    display:block;
    margin:0 auto 2px;
  }

  .meta-info {
    width:90%;
    margin:4px 0;
    font-family:ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Courier New", monospace;
    font-size:13px;
  }

  .meta-row {
    display:flex;
    justify-content:space-between;
    margin-bottom:2px;
    width:100%;
  }

  .meta-row .left  { padding-left:26mm; }
  .meta-row .right { padding-right:26mm; text-align:right; }

  .h-title {
    font-size:22px;
    font-weight:700;
    text-align:center;
    margin:4px 0 8px;
  }

  .table {
    width:90%!important;
    border-collapse:collapse!important;
    background:#ffffff!important;
    margin:0 auto;
    page-break-inside:avoid;
  }

  .table th, .table td {
    border:1px solid #1f2937!important;
    padding:4px 6px!important;
    font-size:11px!important;
    line-height:1.15!important;
    height:22px!important;
    vertical-align:middle!important;
    text-align:left;
  }

  .table th {
    background:#ffffff!important;
    font-size:13px!important;
    height:24px!important;
  }

  .fixed, .sticky, [class*="sticky"], [class*="fixed"] { position:static!important; }

  [class*="h-screen"], [class*="min-h-screen"],
  [style*="height:100vh"], [style*="min-height:100vh"] {
    height:auto!important;
    min-height:0!important;
  }

  * { overflow:visible!important; }
}

/* ===================== SCREEN STYLES ===================== */
body { background-color:#fbfaf8!important; }

.print-root, main, .p-6 {
  background-color:#fbfaf8!important;
  box-shadow:0 2px 8px rgba(0,0,0,0.04);
  padding:20px;
}

.header-image {
  text-align:center;
  margin-bottom:6px;
}

.header-image img {
  height:85px;
  object-fit:contain;
  margin:0 auto;
  display:inline-block;
}

.meta-info {
  width:100%;
  margin:6px 0 6px;
  font-family:ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
  font-size:14px;
}

.meta-row {
  display:flex;
  justify-content:space-between;
  margin-bottom:2px;
}

.meta-row .left  { padding-left:2.5in; }
.meta-row .right { padding-right:2.5in; text-align:left; }

.h-title {
  font-size:28px;
  font-weight:700;
  text-align:center;
  margin:6px 0 10px;
}

.table {
  width:90%;
  border-collapse:collapse;
  background-color:#ffffff;
}

.table th, .table td {
  border:1px solid #1f2937;
  padding:6px 10px;
  font-size:14px;
  line-height:1.3;
  vertical-align:middle;
  text-align:left;
  height:40px;
}

.table th {
  background:#f9fafb;
  font-weight:600;
}

.table td:nth-child(3),
.table td:nth-child(4),
.table td:nth-child(5),
.table td:nth-child(6),
.table td:nth-child(7),
.table td:nth-child(8),
.table td:nth-child(9) {
  text-align:center;
}

.mono {
  font-family:ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
}

.history-banner {
  border:1px dashed #6366f1;
  background:#eef2ff;
  color:#4338ca;
  padding:10px 12px;
  border-radius:8px;
}
</style>

@php
    if (!isset($terms)) {
        $terms = \App\Models\AcademicYear::orderByDesc('id')->get();
    }
    if (!isset($activeId)) {
        $activeId = \App\Models\AcademicYear::where('is_active', 1)->value('id');
    }
    if (!isset($currentTerm)) {
        $currentTerm = $terms->firstWhere('id', (int) $academicId);
    }
@endphp

<div class="print-root p-6 space-y-6">

  @if(!app('request')->isMethod('post') && ($showHistory ?? false))
    <div class="history-banner print:hidden">
      <div class="flex items-start gap-3">
        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
        </svg>
        <div class="text-sm">
          <div class="font-semibold">History View</div>
          <div>
            You’re viewing faculty loads from a previous term.
            @if($currentTerm)
              <span class="font-medium">
                ({{ $currentTerm->school_year }} — {{ $currentTerm->semester }})
              </span>
            @endif
            <span class="text-gray-700">
              Uncheck “History” or choose the active term to go back to the current view.
            </span>
          </div>
        </div>
      </div>
    </div>
  @endif

  {{-- FILTERS: History + Academic Term + Course + Faculty --}}
  <form method="GET" action="{{ url()->current() }}" class="flex flex-wrap items-end gap-3 print:hidden">
      <div class="flex items-center gap-2">
          <input type="checkbox"
                 name="history"
                 value="1"
                 id="history-toggle"
                 class="border rounded"
                 @checked($showHistory ?? false)>
          <label for="history-toggle" class="text-sm">History</label>
      </div>

      <div>
          <label class="block text-xs text-gray-600 mb-1">Academic Term</label>
          <select name="academic" class="border rounded px-3 py-2 w-64">
              @foreach($terms as $a)
                  @php
                      $sem = strtolower((string) $a->semester);
                      $semLabel = in_array($sem,['1','1st','first']) ? '1st Sem'
                                : (in_array($sem,['2','2nd','second']) ? '2nd Sem'
                                : (str_contains($sem,'mid') || $sem === '3' ? 'Midyear' : ucwords($a->semester)));
                  @endphp
                  <option value="{{ $a->id }}" @selected((int) $academicId === (int) $a->id)>
                      {{ $a->school_year }} — {{ $semLabel }} @if($a->id === $activeId) (Active) @endif
                  </option>
              @endforeach
          </select>
      </div>

      <div>
          <label class="block text-xs text-gray-600 mb-1">Course</label>
          <select name="course" class="border rounded px-3 py-2 w-64">
              <option value="">All courses</option>
              @foreach(($courseOptions ?? []) as $opt)
                  <option value="{{ $opt['value'] }}"
                      @selected((int)($courseId ?? 0) === (int) $opt['value'])>
                      {{ $opt['label'] }}
                  </option>
              @endforeach
          </select>
      </div>

      <div>
          <label class="block text-xs text-gray-600 mb-1">Faculty</label>
          <select name="faculty" class="border rounded px-3 py-2 w-64">
              <option value="">All faculty</option>
              @foreach(($facultyOptions ?? []) as $opt)
                  <option value="{{ $opt['value'] }}"
                          @selected((int)($facultyId ?? 0) === (int) $opt['value'])>
                      {{ $opt['label'] }}
                  </option>
              @endforeach
          </select>
      </div>

      <button class="bg-blue-600 text-white rounded px-4 py-2">Apply</button>
      <button type="button"
              onclick="window.print()"
              class="bg-gray-700 text-white rounded px-4 py-2">
          Print
      </button>
  </form>

  {{-- PAGES --}}
  @forelse($sheets as $sheet)
      @php
          $semRaw   = $sheet['semester'] ?? null;
          $semLabel = '—';
          if ($semRaw !== null && $semRaw !== '') {
              $s = trim(strtolower((string) $semRaw));
              if (in_array($s, ['1','1st','first'])) {
                  $semLabel = '1st Semester';
              } elseif (in_array($s, ['2','2nd','second'])) {
                  $semLabel = '2nd Semester';
              } elseif (in_array($s, ['3','3rd','third','summer','midyear','mid-year'])) {
                  $semLabel = (str_contains($s, 'mid')) ? 'Midyear' : 'Summer';
              } else {
                  $semLabel = ucwords($semRaw);
              }
          }
      @endphp

      <div class="page">
          <div class="page-inner">
              <div class="header-image">
                  <img src="{{ asset('images/sfxc_header.png') }}" alt="St. Francis Xavier College Header">
              </div>

              <div class="meta-info">
                <div class="meta-row">
                  <div class="left">
                      School Year :
                      <strong>{{ $sheet['school_year'] ?: '—' }}</strong>
                  </div>
                  <div class="right">
                      Semester :
                      <strong>{{ $semLabel }}</strong>
                  </div>
                </div>
                <div class="meta-row">
                  <div class="left">
                      Instructor :
                      <strong>{{ $sheet['faculty_short'] ?? $sheet['faculty'] ?? '—' }}</strong>
                  </div>
                  <div class="right"></div>
                </div>
              </div>

              <div class="h-title">FACULTY LOADS</div>

              <table class="table">
                  <thead>
                      <tr>
                          <th style="width:100px;">Course Code</th>
                          <th style="width:260px;">Descriptive Title</th>
                          <th style="width:50px;">Units</th>
                          <th style="width:100px;">Section</th>
                          <th style="width:100px;">Start Time</th>
                          <th style="width:100px;">End Time</th>
                          <th style="width:70px;">Days</th>
                          <th style="width:80px;">Room</th>
                          <th style="width:120px;">Instructor</th>
                      </tr>
                  </thead>
                  <tbody>
                      @forelse($sheet['rows'] as $r)
                          <tr>
                              <td class="mono">{{ $r['code'] }}</td>
                              <td>{{ $r['title'] }}</td>
                              <td class="mono">{{ $r['units'] }}</td>
                              <td class="mono">{{ $r['section'] }}</td>
                              <td class="mono">{{ $r['st'] }}</td>
                              <td class="mono">{{ $r['et'] }}</td>
                              <td class="mono">{{ $r['days'] }}</td>
                              <td class="mono">{{ $r['room'] }}</td>
                              <td class="mono">{{ $r['inst'] }}</td>
                          </tr>
                      @empty
                          <tr>
                              <td colspan="9" style="text-align:center;color:#6b7280;">
                                  No loads found for this faculty in this term.
                              </td>
                          </tr>
                      @endforelse
                  </tbody>
              </table>

          </div>
      </div>
  @empty
    @php
        // Build a readable semester label from $currentTerm
        $noSemLabel = '—';
        if (isset($currentTerm) && $currentTerm) {
            $sem = strtolower((string) $currentTerm->semester);
            $noSemLabel = in_array($sem, ['1','1st','first']) ? '1st Sem'
                        : (in_array($sem, ['2','2nd','second']) ? '2nd Sem'
                        : (str_contains($sem, 'mid') || $sem === '3' ? 'Midyear' : ucwords($currentTerm->semester)));
        }
    @endphp

    <div class="text-gray-500">
        No faculty loads for this term.
        @if(isset($currentTerm) && $currentTerm)
            ({{ $currentTerm->school_year }} — {{ $noSemLabel }})
        @endif
    </div>
@endforelse
</div>

