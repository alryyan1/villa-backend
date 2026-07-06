<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
    body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #1a1a1a; }
    h1 { font-size: 18px; margin: 0 0 2px; }
    .meta { color: #666; font-size: 10px; margin-bottom: 16px; }
    h2 { font-size: 14px; margin: 18px 0 4px; border-bottom: 1px solid #ccc; padding-bottom: 2px; }
    .note { color: #444; font-size: 11px; margin-bottom: 6px; }
    table { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
    th, td { border: 1px solid #ddd; padding: 4px 6px; text-align: left; font-size: 11px; }
    th { background: #f2f2f2; }
    .empty { color: #999; font-style: italic; }
</style>
</head>
<body>
    <h1>{{ $pdfTitle }}</h1>
    <div class="meta">Generated {{ $generatedAt }} &mdash; Al Seef Villas</div>

    @foreach ($sections as $section)
        <h2>{{ $section['title'] }}</h2>

        @if (!empty($section['note']))
            <div class="note">{{ $section['note'] }}</div>
        @endif

        @if (empty($section['rows']))
            <div class="empty">No records.</div>
        @else
            <table>
                <thead>
                    <tr>
                        @foreach ($section['columns'] as $col)
                            <th>{{ $col }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach ($section['rows'] as $row)
                        <tr>
                            @foreach ($row as $cell)
                                <td>{{ $cell }}</td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    @endforeach
</body>
</html>
