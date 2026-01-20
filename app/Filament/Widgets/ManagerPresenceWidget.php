<?php

namespace App\Filament\Widgets;

use App\Models\User;
use Filament\Widgets\Widget;

class ManagerPresenceWidget extends Widget
{
    protected static string $view = 'filament.widgets.manager-presence-widget';

    protected static ?string $heading = '👥 Статус менеджеров';

    protected static ?int $sort = 0;

    protected int | string | array $columnSpan = 1;

    protected static ?string $pollingInterval = '30s';

    public function getManagers(): \Illuminate\Database\Eloquent\Collection
    {
        return User::where('role', 'manager')
            ->withCount(['deals' => fn ($q) => $q->whereIn('status', ['New', 'In Progress'])])
            ->orderByDesc('last_activity_at')
            ->get();
    }

    public function getOnlineCount(): int
    {
        return User::where('role', 'manager')
            ->where('last_activity_at', '>=', now()->subMinutes(5))
            ->count();
    }

    public function getTotalManagers(): int
    {
        return User::where('role', 'manager')->count();
    }
}
