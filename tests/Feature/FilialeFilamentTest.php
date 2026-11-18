<?php

namespace Tests\Feature;

use App\Filament\Resources\FilialeResource\Pages\EditFiliale;
use App\Filament\Resources\FilialeResource\Pages\ListFiliales;
use App\Filament\Resources\PriceRuleResource\Pages\CreatePriceRule;
use App\Filament\Resources\PriceRuleResource\Pages\EditPriceRule;
use App\Filament\Resources\PriceRuleResource\Pages\ListPriceRules;
use App\Models\Filiale;
use App\Models\Platform;
use App\Models\PriceRule;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class FilialeFilamentTest extends TestCase
{
    use RefreshDatabase;

    public function test_filiale_edit_page_renders_with_cascading_selects(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_approved' => true]);
        $platform = Platform::query()->create(['name' => 'MD']);
        $goodsType = $platform->goodsTypes()->create(['name' => 'Secco']);
        $filiale = Filiale::query()->create([
            'platform_id' => $platform->id,
            'goods_type_id' => $goodsType->id,
            'name' => 'Filiale Nord',
            'codice' => 'FN01',
        ]);

        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($admin);

        Livewire::test(EditFiliale::class, ['record' => $filiale->getRouteKey()])
            ->assertSuccessful()
            ->assertFormSet([
                'platform_id' => $platform->id,
                'goods_type_id' => $goodsType->id,
            ]);
    }

    public function test_filiale_list_page_renders(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_approved' => true]);

        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($admin);

        Livewire::test(ListFiliales::class)->assertSuccessful();
    }

    public function test_price_rule_pages_render(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_approved' => true]);
        $platform = Platform::query()->create(['name' => 'MD']);
        $goodsType = $platform->goodsTypes()->create(['name' => 'Secco']);
        $rule = PriceRule::query()->create(['name' => 'Regola test', 'bancali' => 23]);
        $rule->steps()->create(['position' => 1, 'goods_type_id' => $goodsType->id, 'price' => 20, 'owner_price' => 25]);

        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($admin);

        Livewire::test(ListPriceRules::class)->assertSuccessful();
        Livewire::test(CreatePriceRule::class)->assertSuccessful();
        Livewire::test(EditPriceRule::class, ['record' => $rule->getRouteKey()])
            ->assertSuccessful()
            ->assertFormSet(['name' => 'Regola test', 'bancali' => 23]);
    }
}
