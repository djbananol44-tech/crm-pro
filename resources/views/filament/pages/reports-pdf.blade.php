<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>CRM Отчёт</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: 'DejaVu Sans', Arial, sans-serif; 
            font-size: 12px;
            line-height: 1.5;
            color: #1e293b;
            padding: 20px;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #6366f1;
            padding-bottom: 20px;
        }
        .header h1 {
            font-size: 24px;
            color: #1e293b;
            margin-bottom: 5px;
        }
        .header p {
            color: #64748b;
            font-size: 14px;
        }
        .stats-grid {
            display: table;
            width: 100%;
            margin-bottom: 30px;
        }
        .stat-box {
            display: table-cell;
            width: 33.33%;
            text-align: center;
            padding: 15px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
        }
        .stat-box h2 {
            font-size: 28px;
            color: #6366f1;
            margin-bottom: 5px;
        }
        .stat-box p {
            color: #64748b;
            font-size: 11px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        th, td {
            padding: 10px 8px;
            text-align: left;
            border-bottom: 1px solid #e2e8f0;
        }
        th {
            background: #f1f5f9;
            font-weight: 600;
            font-size: 11px;
            text-transform: uppercase;
            color: #64748b;
        }
        tr:hover {
            background: #f8fafc;
        }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 10px;
            font-weight: 600;
        }
        .badge-blue { background: #dbeafe; color: #1d4ed8; }
        .badge-yellow { background: #fef3c7; color: #b45309; }
        .badge-green { background: #dcfce7; color: #15803d; }
        .conversion-high { color: #15803d; font-weight: bold; }
        .conversion-mid { color: #b45309; font-weight: bold; }
        .conversion-low { color: #dc2626; font-weight: bold; }
        .footer {
            margin-top: 30px;
            text-align: center;
            color: #94a3b8;
            font-size: 10px;
            border-top: 1px solid #e2e8f0;
            padding-top: 15px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>📊 CRM Pro — Отчёт</h1>
        <p>Период: {{ $period['start'] }} — {{ $period['end'] }}</p>
    </div>

    <div class="stats-grid">
        <div class="stat-box">
            <h2>{{ $totals['total'] }}</h2>
            <p>Всего сделок</p>
        </div>
        <div class="stat-box">
            <h2>{{ $totals['closed'] }}</h2>
            <p>Закрыто успешно</p>
        </div>
        <div class="stat-box">
            <h2>{{ $totals['hot_leads'] }}</h2>
            <p>Горячих лидов (AI)</p>
        </div>
    </div>

    <h3 style="margin-bottom: 10px; color: #1e293b;">Статистика по менеджерам</h3>

    <table>
        <thead>
            <tr>
                <th>Менеджер</th>
                <th class="text-center">Всего</th>
                <th class="text-center">Новые</th>
                <th class="text-center">В работе</th>
                <th class="text-center">Закрыто</th>
                <th class="text-center">Конверсия</th>
                <th class="text-center">Ср. время ответа</th>
            </tr>
        </thead>
        <tbody>
            @foreach($managers as $manager)
            <tr>
                <td>
                    <strong>{{ $manager['manager'] }}</strong><br>
                    <span style="color: #94a3b8; font-size: 10px;">{{ $manager['email'] }}</span>
                </td>
                <td class="text-center"><strong>{{ $manager['total_deals'] }}</strong></td>
                <td class="text-center"><span class="badge badge-blue">{{ $manager['new_deals'] }}</span></td>
                <td class="text-center"><span class="badge badge-yellow">{{ $manager['in_progress'] }}</span></td>
                <td class="text-center"><span class="badge badge-green">{{ $manager['closed_deals'] }}</span></td>
                <td class="text-center">
                    <span class="{{ $manager['conversion'] >= 50 ? 'conversion-high' : ($manager['conversion'] >= 25 ? 'conversion-mid' : 'conversion-low') }}">
                        {{ $manager['conversion'] }}%
                    </span>
                </td>
                <td class="text-center">
                    @if($manager['avg_response_time'])
                        {{ $manager['avg_response_time'] }} мин
                    @else
                        —
                    @endif
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div class="footer">
        <p>Сгенерировано: {{ now()->format('d.m.Y H:i') }} | CRM Pro System</p>
    </div>
</body>
</html>
