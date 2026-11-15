<?php

use App\Enums\PostStatus;
use App\Enums\RoleName;
use App\Filament\Widgets\ContentQueueWidget;
use App\Filament\Widgets\MyPostsChart;
use App\Filament\Widgets\PostsPublishedChart;
use App\Filament\Widgets\PostStatusChart;
use App\Models\Post;

use function Pest\Laravel\actingAs;

it('widget konten tampil sesuai hak akses', function (): void {
    $creators = [RoleName::KoordinatorMedpub, RoleName::AnggotaMedpub, RoleName::KoordinatorHumas, RoleName::AnggotaHumas, RoleName::KoordinatorKajian, RoleName::AnggotaKajian];

    foreach (RoleName::cases() as $role) {
        actingAs(member($role));

        expect(PostStatusChart::canView())->toBe(in_array($role, [RoleName::SuperAdmin, RoleName::KoordinatorMedpub, RoleName::Pembina], true), "status {$role->value}")
            ->and(ContentQueueWidget::canView())->toBe(in_array($role, [RoleName::SuperAdmin, RoleName::KoordinatorMedpub], true), "antrean {$role->value}")
            ->and(MyPostsChart::canView())->toBe(in_array($role, $creators, true), "milik saya {$role->value}")
            ->and(PostsPublishedChart::canView())->toBe(in_array($role, [RoleName::SuperAdmin, RoleName::Pembina, ...$creators], true), "terbit {$role->value}");
    }
});

it('Konten saya hanya menghitung konten milik user; pembina tidak melihat draft orang lain', function (): void {
    $saya = member(RoleName::AnggotaMedpub);
    Post::factory()->count(2)->create(['author_id' => $saya->id, 'status' => PostStatus::Draft]);
    Post::factory()->create(['author_id' => member(RoleName::AnggotaMedpub)->id, 'status' => PostStatus::Draft]);
    Post::factory()->create(['author_id' => member(RoleName::AnggotaHumas)->id, 'status' => PostStatus::PendingLeader]);

    actingAs($saya);
    $mine = (fn () => $this->getData())->call(livewire(MyPostsChart::class)->instance());
    expect(array_sum($mine['datasets'][0]['data']))->toBe(2);

    actingAs(member(RoleName::Pembina));
    $all = (fn () => $this->getData())->call(livewire(PostStatusChart::class)->instance());
    expect($all['labels'])->toBe(['Menunggu Ketua/Wakil']);

    actingAs(member(RoleName::KoordinatorMedpub));
    $koor = (fn () => $this->getData())->call(livewire(PostStatusChart::class)->instance());
    expect(array_sum($koor['datasets'][0]['data']))->toBe(4);
});

it('antrean konten per peran', function (): void {
    $koor = member(RoleName::KoordinatorMedpub);
    $author = member(RoleName::AnggotaMedpub);
    Post::factory()->create(['author_id' => $author->id, 'status' => PostStatus::PendingCoordinator]);
    Post::factory()->create(['author_id' => $author->id, 'status' => PostStatus::PendingLeader]);
    Post::factory()->published()->create(['author_id' => $author->id]);

    actingAs($koor);
    $stats = collect((fn () => $this->getStats())->call(livewire(ContentQueueWidget::class)->instance()))
        ->mapWithKeys(fn ($s) => [$s->getLabel() => $s->getValue()]);
    expect($stats['Menunggu persetujuan saya'])->toBe(1)->and($stats['Terbit, belum diposting ke medsos'])->toBe(1);

    actingAs(superAdmin());
    $stats = collect((fn () => $this->getStats())->call(livewire(ContentQueueWidget::class)->instance()))
        ->mapWithKeys(fn ($s) => [$s->getLabel() => $s->getValue()]);
    expect($stats['Menunggu persetujuan Ketua/Wakil'])->toBe(1);
});

it('grafik konten tampil kosong dengan rapi', function (): void {
    actingAs(member(RoleName::KoordinatorMedpub));

    foreach ([PostStatusChart::class, PostsPublishedChart::class, MyPostsChart::class] as $widget) {
        expect(livewire($widget)->instance()->isEmpty())->toBeTrue($widget);
    }
});
