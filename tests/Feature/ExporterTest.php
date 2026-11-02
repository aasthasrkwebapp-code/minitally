<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ExporterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-10-28 12:00:00');
        $this->actingAs(User::factory()->create());
    }

    public function test_exporter_is_saved_with_dgft_icegate_and_dsc_details(): void
    {
        $this->post(route('clients.store'), [
            'name' => 'Jaipur Gems Overseas',
            'email' => 'jaipurgems@example.com',
            'whatsapp' => '+91 9800000000',
            'address' => 'MI Road, Jaipur',
            'dgft_user_id' => 'JGO123',
            'dgft_password' => 'dgft-secret',
            'icegate_id' => 'ICE456',
            'icegate_password' => 'ice-secret',
            'dsc_valid_till' => '2026-11-10',
            'dsc_status' => 'available',
            'is_active' => '1',
        ])->assertRedirect();

        $exporter = Client::firstOrFail();
        $this->assertSame('dgft-secret', $exporter->dgft_password);
        $this->assertSame('ice-secret', $exporter->icegate_password);
        $this->assertSame('2026-11-10', $exporter->dsc_valid_till->format('Y-m-d'));

        // Passwords are encrypted in the database and kept out of arrays/JSON
        $raw = DB::table('clients')->first();
        $this->assertNotSame('dgft-secret', $raw->dgft_password);
        $this->assertArrayNotHasKey('dgft_password', $exporter->toArray());

        $this->assertSame('expiring', $exporter->dscAlert());

        $this->get(route('clients.index'))->assertOk()
            ->assertSee('Exporters')->assertSee('Add exporter')
            ->assertSee('DSC ends in 13 days')->assertDontSee('dgft-secret');
        $this->get(route('clients.show', $exporter))->assertOk()->assertSee('JGO123')->assertSee('ICE456');
        $this->get(route('clients.edit', $exporter))->assertOk()->assertSee('Edit exporter')->assertSee('Delete exporter');
    }

    public function test_dsc_alerts_and_old_links(): void
    {
        $expired = Client::create(['name' => 'Old DSC', 'dsc_status' => 'expired']);
        $past = Client::create(['name' => 'Past date', 'dsc_valid_till' => '2026-10-01', 'dsc_status' => 'delivered']);
        $fine = Client::create(['name' => 'Fine DSC', 'dsc_valid_till' => '2027-06-01', 'dsc_status' => 'available']);

        $this->assertSame('expired', $expired->dscAlert());
        $this->assertSame('expired', $past->dscAlert());
        $this->assertSame('ok', $fine->dscAlert());

        $this->get(route('clients.index', ['dsc' => 'alert']))->assertOk()
            ->assertSee('Old DSC')->assertSee('Past date')->assertDontSee('Fine DSC');

        $this->assertStringEndsWith('/exporters', route('clients.index'));
        $this->get('/clients/'.$fine->id)->assertRedirect('/exporters/'.$fine->id);
    }

    public function test_dsc_status_must_be_known(): void
    {
        $this->post(route('clients.store'), ['name' => 'X', 'dsc_status' => 'lost'])
            ->assertSessionHasErrors('dsc_status');
    }
}
