<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Entry;
use App\Models\User;
use App\Services\SampleFiles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Csv;
use PhpOffice\PhpSpreadsheet\Writer\Xls;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

/** The office's own Excel sheet, as typed by people: title rows, mixed date and amount styles, totals, blanks. */
class EntryImportTest extends TestCase
{
    use RefreshDatabase;

    private const HEADINGS = ['S.NO', 'ISSUED DATE', 'PARTY NAME', 'REFERENCE NO.', 'INVOICE NUMBER', 'IR/NA', 'DESTINATION', 'CERTIFICATE', 'AMOUNT', 'TRANSACTION AMOUNT', 'MAIL BY'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
    }

    private function messySheet(): Spreadsheet
    {
        $book = new Spreadsheet;
        $sheet = $book->getActiveSheet();
        $sheet->fromArray([
            ['RAHUL CHANDAK - DAILY CERTIFICATE REGISTER'],
            ['October 2026'],
            [],
            self::HEADINGS,
            [1, ExcelDate::PHPToExcel(new \DateTime('2026-10-01')), 'Shree Balaji Exports', 'R001', 'EXP/001', 'IR', 'Bangladesh', 'COO - SAFTA', 1200, 250000, 'Rahul'],
            [2, '02/10/2026', 'Jaipur Gems Overseas', 'R002', 'EXP/002', 'NA', 'UAE', 'Embassy Legalisation', '2,500', '1,25,000', 'Aastha'],
            [3, '03-Oct-2026', ' Shree Balaji Exports ', 'R003', 'EXP/003', 'NA', 'USA', 'COO', '₹ 750', '', 'Office'],
            [4, '04.10.26', 'Mehta Textiles', '', '', '', '', '', 500.5, null, ''],
            [],
            [5, '05/10/2026', 'Kota Stone Traders', 'R005', 'EXP/005', 'NA', 'Qatar', 'COO', '', '', 'Rahul'],   // no amount
            [6, 'not a date', 'Kota Stone Traders', 'R006', 'EXP/006', 'NA', 'Qatar', 'COO', 750, '', 'Rahul'],  // bad date
            [7, '06/10/2026', '', 'R007', 'EXP/007', 'NA', 'Qatar', 'COO', 750, '', 'Rahul'],                    // no party
            ['', '', '', '', '', '', '', 'TOTAL', '=SUM(I5:I8)', '', ''],
        ]);

        return $book;
    }

    private function upload(Spreadsheet $book, string $ext = 'xlsx')
    {
        $path = sys_get_temp_dir().'/'.uniqid('imp').'.'.$ext;
        match ($ext) {
            'csv' => (new Csv($book))->save($path),
            'xls' => (new Xls($book))->save($path),
            default => (new Xlsx($book))->save($path),
        };

        return $this->post(route('entries.import.store'), ['file' => new UploadedFile($path, 'register.'.$ext, null, null, true)]);
    }

    public function test_messy_office_sheet_imports_correctly(): void
    {
        $this->upload($this->messySheet())->assertRedirect(route('entries.index'))
            ->assertSessionHas('ok', fn ($msg) => str_contains($msg, '4 entries imported') && str_contains($msg, '3 rows skipped') && str_contains($msg, 'Excel row 10, 11, 12'));

        $this->assertSame(4, Entry::count());
        $e = Entry::orderBy('issued_date')->get();
        $this->assertSame(['2026-10-01', '2026-10-02', '2026-10-03', '2026-10-04'], $e->map(fn ($x) => $x->issued_date->toDateString())->all());
        $this->assertEquals([1200, 2500, 750, 500.5], $e->map(fn ($x) => (float) $x->amount)->all());
        $this->assertEquals(125000, (float) $e[1]->transaction_amount);
        $this->assertNull($e[2]->transaction_amount);
        $this->assertSame(['R001', 'EXP/001', 'IR', 'Bangladesh', 'COO - SAFTA', 'Rahul'], [$e[0]->reference_no, $e[0]->invoice_number, $e[0]->ir_na, $e[0]->destination, $e[0]->certificate, $e[0]->mail_by]);

        // Extra spaces in the party name still land on the same client
        $this->assertSame(3, Client::count());
        $this->assertSame($e[0]->client_id, $e[2]->client_id);
    }

    public function test_importing_the_same_sheet_again_adds_only_new_rows(): void
    {
        $this->upload($this->messySheet());
        $this->assertSame(4, Entry::count());

        $this->upload($this->messySheet())->assertSessionHas('ok', fn ($msg) => str_contains($msg, '0 entries imported') && str_contains($msg, '4 rows were already'));
        $this->assertSame(4, Entry::count());

        // Next day the same file has one more row
        $book = $this->messySheet();
        $book->getActiveSheet()->insertNewRowBefore(9)->fromArray([8, '07/10/2026', 'Mehta Textiles', 'R008', 'EXP/008', 'IR', 'Nepal', 'COO - SAFTA', 1200, '', 'Rahul'], null, 'A9');
        $this->upload($book)->assertSessionHas('ok', fn ($msg) => str_contains($msg, '1 entries imported'));
        $this->assertSame(5, Entry::count());
    }

    public function test_two_identical_rows_in_one_sheet_are_both_kept(): void
    {
        $book = new Spreadsheet;
        $book->getActiveSheet()->fromArray([self::HEADINGS,
            [1, '01/10/2026', 'Mehta Textiles', '', '', 'NA', 'UAE', 'COO', 750, '', ''],
            [2, '01/10/2026', 'Mehta Textiles', '', '', 'NA', 'UAE', 'COO', 750, '', ''],
        ]);
        $this->upload($book);
        $this->assertSame(2, Entry::count());
        $this->upload($book);
        $this->assertSame(2, Entry::count());
    }

    public function test_csv_and_old_xls_files_work_and_columns_can_be_in_any_order(): void
    {
        $book = new Spreadsheet;
        $book->getActiveSheet()->fromArray([
            ['AMOUNT', 'PARTY NAME', 'ISSUED DATE', 'Remarks'],   // only required columns, shuffled, plus an unknown one
            [900, 'Sanganer Block Prints', '10/10/2026', 'urgent'],
        ]);
        $this->upload($book, 'csv');
        $this->assertSame(1, Entry::count());
        $this->assertEquals(900, (float) Entry::first()->amount);

        $this->upload($this->messySheet(), 'xls');
        $this->assertSame(5, Entry::count());
    }

    public function test_sheet_without_needed_columns_gives_a_clear_message(): void
    {
        $book = new Spreadsheet;
        $book->getActiveSheet()->fromArray([['Name', 'Value'], ['x', 1]]);
        $this->upload($book)->assertSessionHasErrors(['file' => 'Could not find the heading row. The sheet needs at least: ISSUED DATE, PARTY NAME, AMOUNT.']);
        $this->assertSame(0, Entry::count());
    }

    public function test_sample_download_has_50_rows_in_the_office_format(): void
    {
        $response = $this->get(route('entries.sample'))->assertOk();
        $rows = \App\Support\Sheet::rows($response->baseResponse->getFile()->getPathname());
        $this->assertSame(SampleFiles::HEADINGS, $rows[0]);
        $this->assertCount(52, $rows); // heading + 50 rows + total
    }

    public function test_manual_entry_with_all_columns_shows_in_list_and_can_be_edited(): void
    {
        $row = ['party_name' => 'Marudhar Spices LLP', 'reference_no' => 'R777', 'invoice_number' => 'EXP/777', 'ir_na' => 'IR',
            'destination' => 'Vietnam', 'certificate' => 'COO - AIFTA', 'amount' => '1500', 'transaction_amount' => '330000', 'mail_by' => 'Aastha'];
        $this->post(route('entries.store'), ['issued_date' => '2026-10-12', 'rows' => [$row, ['party_name' => '', 'amount' => '']]])
            ->assertSessionHasNoErrors();

        $entry = Entry::sole();
        $this->get(route('entries.index', ['month' => '2026-10']))->assertOk()
            ->assertSeeInOrder(['12-10-2026', 'Marudhar Spices LLP', 'R777', 'EXP/777', 'IR', 'Vietnam', 'COO - AIFTA', '1,500.00', '3,30,000.00', 'Aastha']);

        $this->get(route('entries.edit', $entry))->assertOk()->assertSee('value="R777"', false);
        $this->put(route('entries.update', $entry), array_merge($row, ['issued_date' => '2026-10-13', 'amount' => '1750']))->assertSessionHasNoErrors();
        $this->assertEquals(1750, (float) $entry->fresh()->amount);
        $this->assertSame('2026-10-13', $entry->fresh()->issued_date->toDateString());

        $this->delete(route('entries.destroy', $entry));
        $this->assertSame(0, Entry::count());
    }
}
