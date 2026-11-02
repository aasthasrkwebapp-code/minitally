<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Entry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StaffLoginTest extends TestCase
{
    use RefreshDatabase;

    private function staff(): User
    {
        return User::factory()->create(['role' => User::ROLE_STAFF]);
    }

    private function entry(): Entry
    {
        $client = Client::create(['name' => 'Acme Exports']);

        return Entry::create([
            'client_id' => $client->id, 'issued_date' => now()->toDateString(), 'party_name' => 'Acme Exports',
            'reference_no' => 'REF-77', 'certificate' => 'COO', 'amount' => 98765.43, 'transaction_amount' => 12345.67,
        ]);
    }

    public function test_staff_lands_on_entries_and_sees_no_amounts(): void
    {
        $this->entry();
        $staff = $this->staff();

        $this->post('/login', ['email' => $staff->email, 'password' => 'password'])
            ->assertRedirect(route('entries.index'));

        $this->get(route('entries.index'))->assertOk()
            ->assertSee('Acme Exports')->assertSee('REF-77')
            ->assertDontSee('98,765')->assertDontSee('12,345')->assertDontSee('Amount')
            ->assertDontSee(route('entries.create'))->assertDontSee('Month-end billing');
    }

    public function test_staff_cannot_open_anything_else_or_change_entries(): void
    {
        $entry = $this->entry();
        $this->actingAs($this->staff());

        foreach (['dashboard', 'entries.create', 'entries.import', 'billing.index', 'invoices.index', 'payments.index',
            'bank.index', 'outstanding.index', 'clients.index', 'settings.edit'] as $route) {
            $this->get(route($route))->assertRedirect(route('entries.index'));
        }
        $this->get(route('entries.edit', $entry))->assertRedirect(route('entries.index'));
        $this->put(route('entries.update', $entry), ['issued_date' => now()->toDateString(), 'party_name' => 'X', 'amount' => 1])
            ->assertRedirect(route('entries.index'));
        $this->delete(route('entries.destroy', $entry))->assertRedirect(route('entries.index'));
        $this->post(route('staff.store'), ['name' => 'Sneaky', 'email' => 'sneaky@example.com', 'password' => 'secret123'])
            ->assertRedirect(route('entries.index'));

        $this->assertSame('Acme Exports', $entry->fresh()->party_name);
        $this->assertDatabaseMissing('users', ['email' => 'sneaky@example.com']);
    }

    public function test_admin_still_sees_amounts(): void
    {
        $this->entry();
        $this->actingAs(User::factory()->create());

        $this->get(route('entries.index'))->assertOk()->assertSee('98,765')->assertSee(route('entries.create'));
    }

    public function test_admin_manages_up_to_five_staff_logins(): void
    {
        $this->actingAs(User::factory()->create());

        for ($i = 1; $i <= 5; $i++) {
            $this->post(route('staff.store'), ['name' => "Staff $i", 'email' => "staff$i@example.com", 'password' => 'secret123'])
                ->assertSessionHasNoErrors();
        }
        $this->post(route('staff.store'), ['name' => 'Staff 6', 'email' => 'staff6@example.com', 'password' => 'secret123'])
            ->assertSessionHasErrors('staff');
        $this->assertSame(5, User::staff()->count());

        $this->get(route('settings.edit'))->assertOk()->assertSee('Staff logins (5 of 5)')->assertSee('staff3@example.com');

        $one = User::where('email', 'staff1@example.com')->first();
        $this->put(route('staff.update', $one), ['name' => 'Priya', 'email' => 'priya@example.com', 'password' => 'newpass123'])
            ->assertSessionHasNoErrors();
        $this->assertSame('Priya', $one->fresh()->name);
        $this->assertTrue(\Hash::check('newpass123', $one->fresh()->password));

        $this->delete(route('staff.destroy', $one))->assertSessionHasNoErrors();
        $this->assertSame(4, User::staff()->count());

        // The owner's own login can't be edited or deleted from the staff form
        $admin = auth()->user();
        $this->delete(route('staff.destroy', $admin))->assertNotFound();
    }
}
