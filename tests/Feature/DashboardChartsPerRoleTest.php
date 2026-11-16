<?php

use App\Enums\RoleName;
use Filament\Facades\Filament;
use Filament\Widgets\ChartWidget;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

/**
 * AGENTS.md C1 / Fase 10 (4a): SETIAP role — termasuk Pembina dan pengaju kegiatan — punya minimal satu
 * widget grafik di dashboard, bahkan pada instalasi baru tanpa data (grafik menampilkan tampilan kosong).
 */
it('setiap role punya minimal satu grafik di dashboard', function (RoleName $role): void {
    $user = member($role);
    actingAs($user);

    $charts = collect(Filament::getPanel('admin')->getWidgets())
        ->filter(fn (string $widget) => is_subclass_of($widget, ChartWidget::class) && $widget::canView())
        ->map(fn (string $widget) => class_basename($widget))
        ->values();

    expect($charts)->not->toBeEmpty("Role {$role->value} tidak punya grafik di dashboard");

    get('/panel-bem')->assertOk();
})->with(RoleName::cases());
