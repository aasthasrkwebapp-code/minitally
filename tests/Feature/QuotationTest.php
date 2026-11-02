<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Entry;
use App\Models\Quotation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuotationTest extends TestCase
{
    use RefreshDatabase;

    private Client $exporter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-11-02 12:00:00');
        $this->actingAs(User::factory()->create());
        $this->exporter = Client::create(['name' => 'Jaipur Gems Overseas']);
    }

    private function makeQuotation(string $status = 'accepted'): Quotation
    {
        $this->post(route('quotations.store'), [
            'client_id' => $this->exporter->id,
            'quote_date' => '2026-11-01',
            'valid_until' => '2026-12-31',
            'items' => [
                ['certificate' => 'Certificate of Origin (Non-Preferential)', 'ir_na' => 'NA', 'sets' => 1, 'rate' => 750],
                ['certificate' => 'Certificate of Origin (Non-Preferential)', 'ir_na' => 'NA', 'sets' => 3, 'rate' => 1500],
                ['certificate' => 'Certificate of Origin (Non-Preferential)', 'ir_na' => 'IR', 'sets' => 1, 'rate' => 1200],
                ['certificate' => '', 'rate' => ''], // empty row is ignored
            ],
        ])->assertRedirect();

        $quotation = Quotation::firstOrFail();
        if ($status !== 'draft') {
            $this->post(route('quotations.status', $quotation), ['status' => $status])->assertRedirect();
        }

        return $quotation->fresh();
    }

    public function test_quotation_is_saved_numbered_and_shown(): void
    {
        $q = $this->makeQuotation('draft');

        $this->assertSame('QT/26-27/0001', $q->number);
        $this->assertSame('draft', $q->status);
        $this->assertCount(3, $q->items);
        $this->get(route('quotations.index'))->assertOk()->assertSee('QT/26-27/0001');
        $this->get(route('quotations.show', $q))->assertOk()->assertSee('IR (Retrospective)')->assertSee('Normal');
        $this->get(route('quotations.create'))->assertOk();
        $this->get(route('quotations.edit', $q))->assertOk();
        $this->get(route('quotations.pdf', $q))->assertOk()->assertHeader('content-type', 'application/pdf');
    }

    public function test_accepted_rate_fills_a_blank_entry_amount_and_links_the_quote(): void
    {
        $q = $this->makeQuotation();

        $this->post(route('entries.store'), ['issued_date' => '2026-11-02', 'rows' => [
            ['party_name' => 'Jaipur Gems Overseas', 'certificate' => 'certificate of origin (non-preferential)', 'ir_na' => 'NA', 'sets' => 3, 'amount' => ''],
            ['party_name' => 'Jaipur Gems Overseas', 'certificate' => 'Certificate of Origin (Non-Preferential)', 'ir_na' => 'IR', 'sets' => 1, 'amount' => ''],
            ['party_name' => 'Jaipur Gems Overseas', 'certificate' => 'Certificate of Origin (Non-Preferential)', 'ir_na' => 'NA', 'sets' => 1, 'amount' => '900'],
        ]])->assertRedirect();

        $entries = Entry::orderBy('id')->get();
        $this->assertSame(['1500.00', '1200.00', '900.00'], $entries->pluck('amount')->all());
        $this->assertSame([$q->id, $q->id, null], $entries->pluck('quotation_id')->all());
        $this->assertSame(3, $entries[0]->sets);
        $this->get(route('quotations.show', $q))->assertSee('Daily entries at these rates (2)');
    }

    public function test_draft_or_rejected_quotes_do_not_fill_amounts(): void
    {
        $this->makeQuotation('rejected');

        $this->post(route('entries.store'), ['issued_date' => '2026-11-02', 'rows' => [
            ['party_name' => 'Jaipur Gems Overseas', 'certificate' => 'Certificate of Origin (Non-Preferential)', 'ir_na' => 'NA', 'sets' => 1, 'amount' => ''],
        ]])->assertSessionHasErrors('rows.0.amount');
        $this->assertSame(0, Entry::count());
    }

    public function test_rate_lookup_for_the_entry_form(): void
    {
        $q = $this->makeQuotation();

        $this->getJson(route('quotations.rate', ['party_name' => 'jaipur gems overseas', 'certificate' => 'Certificate of Origin (Non-Preferential)', 'ir_na' => 'IR', 'sets' => 1, 'issued_date' => '2026-11-02']))
            ->assertOk()->assertJson(['rate' => 1200, 'quotation_id' => $q->id, 'number' => 'QT/26-27/0001']);

        // Past the valid-until date there is no rate
        $this->getJson(route('quotations.rate', ['party_name' => 'Jaipur Gems Overseas', 'certificate' => 'Certificate of Origin (Non-Preferential)', 'ir_na' => 'NA', 'sets' => 1, 'issued_date' => '2027-01-05']))
            ->assertOk()->assertExactJson([]);

        // Unknown party is not created by a lookup
        $this->getJson(route('quotations.rate', ['party_name' => 'Someone New', 'certificate' => 'X']))->assertOk();
        $this->assertSame(1, Client::count());
    }

    public function test_deleting_a_quotation_keeps_entry_amounts(): void
    {
        $q = $this->makeQuotation();
        $this->post(route('entries.store'), ['issued_date' => '2026-11-02', 'rows' => [
            ['party_name' => 'Jaipur Gems Overseas', 'certificate' => 'Certificate of Origin (Non-Preferential)', 'ir_na' => 'NA', 'sets' => 1, 'amount' => ''],
        ]]);

        $this->delete(route('quotations.destroy', $q))->assertRedirect(route('quotations.index'));
        $entry = Entry::firstOrFail();
        $this->assertSame('750.00', $entry->amount);
        $this->assertNull($entry->quotation_id);
    }
}
