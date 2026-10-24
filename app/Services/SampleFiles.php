<?php

namespace App\Services;

use Carbon\Carbon;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/** Dummy data and downloadable sample Excel files (daily entries and bank statement). */
class SampleFiles
{
    public const CLIENTS = [
        ['name' => 'Exporter Adda', 'contact_person' => 'Rahul Ji', 'email' => 'exporteradda@gmail.com', 'whatsapp' => '+918114414800', 'bank_keywords' => 'EXPORTERADDA'],
        ['name' => 'Shree Balaji Exports', 'contact_person' => 'Mr. Agarwal', 'email' => 'balaji@example.com', 'whatsapp' => null, 'bank_keywords' => 'BALAJI EXPORTS'],
        ['name' => 'Jaipur Gems Overseas', 'contact_person' => 'Mr. Jain', 'email' => 'jaipurgems@example.com', 'whatsapp' => null, 'bank_keywords' => null],
        ['name' => 'Rajasthan Handicrafts Pvt Ltd', 'contact_person' => 'Mrs. Sharma', 'email' => 'rhpl@example.com', 'whatsapp' => null, 'bank_keywords' => 'RAJ HANDICRAFTS'],
        ['name' => 'Mehta Textiles', 'contact_person' => 'Mr. Mehta', 'email' => 'mehtatex@example.com', 'whatsapp' => null, 'bank_keywords' => null],
        ['name' => 'Sanganer Block Prints', 'contact_person' => 'Mr. Chhipa', 'email' => 'sanganer@example.com', 'whatsapp' => null, 'bank_keywords' => null],
        ['name' => 'Kota Stone Traders', 'contact_person' => 'Mr. Gupta', 'email' => 'kotastone@example.com', 'whatsapp' => null, 'bank_keywords' => null],
        ['name' => 'Marudhar Spices LLP', 'contact_person' => 'Mr. Choudhary', 'email' => 'marudhar@example.com', 'whatsapp' => null, 'bank_keywords' => null],
    ];

    private const DESTINATIONS = ['UAE', 'Bangladesh', 'Sri Lanka', 'Nepal', 'Vietnam', 'Malaysia', 'Saudi Arabia', 'USA', 'UK', 'Germany', 'Japan', 'Qatar', 'Egypt'];

    /** [IR/NA, certificate, our fee, countries it applies to or null for any] */
    private const CERTS = [
        ['IR', 'COO - SAFTA', 1200, ['Bangladesh', 'Sri Lanka', 'Nepal']],
        ['IR', 'COO - AIFTA (ASEAN)', 1500, ['Vietnam', 'Malaysia']],
        ['IR', 'COO - India Japan CEPA', 1500, ['Japan']],
        ['IR', 'COO - India UAE CEPA', 1500, ['UAE']],
        ['NA', 'Certificate of Origin (Non-Preferential)', 750, null],
        ['NA', 'Invoice & Packing List Attestation', 500, null],
        ['NA', 'Embassy Legalisation', 2500, ['UAE', 'Saudi Arabia', 'Qatar', 'Egypt']],
    ];

    private const MAIL_BY = ['Rahul', 'Aastha', 'Office'];

    public const HEADINGS = ['S.NO', 'ISSUED DATE', 'PARTY NAME', 'REFERENCE NO.', 'INVOICE NUMBER', 'IR/NA', 'DESTINATION', 'CERTIFICATE', 'AMOUNT', 'TRANSACTION AMOUNT', 'MAIL BY'];

    /** 50 deterministic dummy entries spread over the month (days 1-24), using the office's columns. */
    public function dummyEntries(?string $period = null, int $count = 50): array
    {
        $period ??= now()->format('Y-m');
        $start = Carbon::createFromFormat('Y-m-d', $period.'-01');
        $lastDay = min(24, $start->daysInMonth);
        mt_srand(2026);

        $rows = [];
        for ($i = 0; $i < $count; $i++) {
            $party = self::CLIENTS[$i % 3 === 0 ? 0 : mt_rand(1, count(self::CLIENTS) - 1)]['name'];
            $destination = self::DESTINATIONS[mt_rand(0, count(self::DESTINATIONS) - 1)];
            $options = array_values(array_filter(self::CERTS, fn ($c) => $c[3] === null || in_array($destination, $c[3])));
            [$irna, $cert, $amount] = $options[mt_rand(0, count($options) - 1)];

            $rows[] = [
                'issued_date' => $start->copy()->day(1 + intdiv($i * $lastDay, $count))->toDateString(),
                'party_name' => $party,
                'reference_no' => 'R'.$start->format('ym').str_pad((string) (3001 + $i * 7), 5, '0', STR_PAD_LEFT),
                'invoice_number' => 'EXP/'.$start->format('y').'/'.str_pad((string) (101 + $i), 4, '0', STR_PAD_LEFT),
                'ir_na' => $irna,
                'destination' => $destination,
                'certificate' => $cert,
                'amount' => $amount,
                'transaction_amount' => mt_rand(5, 400) * 1000,
                'mail_by' => self::MAIL_BY[mt_rand(0, count(self::MAIL_BY) - 1)],
            ];
        }
        mt_srand();

        return $rows;
    }

    public function entriesWorkbook(?string $path = null, ?string $period = null): string
    {
        $book = new Spreadsheet;
        $sheet = $book->getActiveSheet()->setTitle('Daily Entries');
        $sheet->fromArray(self::HEADINGS, null, 'A1');

        $r = 2;
        foreach ($this->dummyEntries($period) as $i => $e) {
            $sheet->fromArray([
                $i + 1, ExcelDate::PHPToExcel(Carbon::parse($e['issued_date'])), $e['party_name'], $e['reference_no'],
                $e['invoice_number'], $e['ir_na'], $e['destination'], $e['certificate'], $e['amount'],
                $e['transaction_amount'], $e['mail_by'],
            ], null, 'A'.$r++);
        }
        $sheet->setCellValue('H'.$r, 'TOTAL');
        $sheet->setCellValue('I'.$r, '=SUM(I2:I'.($r - 1).')');

        $sheet->getStyle('B2:B'.$r)->getNumberFormat()->setFormatCode('dd-mm-yyyy');
        $sheet->getStyle('I2:J'.$r)->getNumberFormat()->setFormatCode('#,##0.00');
        $this->styleHeader($sheet, 'A1:K1');
        $sheet->getStyle('H'.$r.':I'.$r)->getFont()->setBold(true);
        foreach (range('A', 'K') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
        $sheet->freezePane('A2');

        return $this->save($book, $path);
    }

    /** A PNB-style statement with client payments for the dummy month, plus some expenses. */
    public function bankStatement(?string $path = null, ?string $period = null): string
    {
        $period ??= now()->format('Y-m');
        $totals = collect($this->dummyEntries($period))->groupBy('party_name')->map(fn ($g) => $g->sum('amount'));
        $d = Carbon::createFromFormat('Y-m-d', $period.'-01')->addMonth();
        $mon = strtoupper(Carbon::createFromFormat('Y-m-d', $period.'-01')->format('M'));

        $txns = [
            [$d->copy()->day(2), "NEFT-SBIN0003456-SHREE BALAJI EXPORTS-$mon BILL", 0, $totals['Shree Balaji Exports'] ?? 0],
            [$d->copy()->day(3), 'UPI/629812345678/EXPORTERADDA/exporteradda@okaxis/Payment', 0, $totals['Exporter Adda'] ?? 0],
            [$d->copy()->day(3), 'ATM CASH WDL JAIPUR', 5000, 0],
            [$d->copy()->day(4), 'IMPS/P2A/630011122233/JAIPUR GEMS OVERSEAS/HDFC', 0, round(($totals['Jaipur Gems Overseas'] ?? 0) / 2)],
            [$d->copy()->day(5), 'NEFT-ICIC0000123-RAJ HANDICRAFTS PVT-INV', 0, $totals['Rajasthan Handicrafts Pvt Ltd'] ?? 0],
            [$d->copy()->day(5), 'BIL/ONL/JVVNL ELECTRICITY', 2340, 0],
            [$d->copy()->day(6), 'UPI/630155566677/RAMESH KUMAR/ramesh@ybl/xx', 0, $totals['Mehta Textiles'] ?? 0],
            [$d->copy()->day(7), 'BY CLG CHQ 004512 UNKNOWN PARTY', 0, 3333],
        ];

        $book = new Spreadsheet;
        $sheet = $book->getActiveSheet()->setTitle('Statement');
        $sheet->setCellValue('A1', 'PUNJAB NATIONAL BANK - Account Statement');
        $sheet->setCellValue('A2', 'Account No: 4087000100127064    Name: RAHUL CHANDAK');
        $sheet->setCellValue('A3', 'Period: '.$d->copy()->startOfMonth()->format('d/m/Y').' to '.$d->copy()->day(7)->format('d/m/Y'));
        $sheet->fromArray(['Txn No.', 'Txn Date', 'Description', 'Branch Name', 'Cheque No.', 'Dr Amount', 'Cr Amount', 'Balance', 'Value Date'], null, 'A5');

        $balance = 25000;
        $r = 6;
        foreach ($txns as $i => [$date, $desc, $dr, $cr]) {
            $balance += $cr - $dr;
            $sheet->fromArray([
                'S'.(81234560 + $i), $date->format('d/m/Y'), $desc, 'JAIPUR MAIN', '',
                $dr ?: '', $cr ?: '', number_format($balance, 2, '.', '').' Cr.', $date->format('d/m/Y'),
            ], null, 'A'.$r++);
        }
        $this->styleHeader($sheet, 'A5:I5');
        foreach (range('A', 'I') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        return $this->save($book, $path);
    }

    private function styleHeader($sheet, string $range): void
    {
        $style = $sheet->getStyle($range);
        $style->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $style->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('34467C');
    }

    private function save(Spreadsheet $book, ?string $path): string
    {
        $path ??= sys_get_temp_dir().'/'.uniqid('sample_', true).'.xlsx';
        (new Xlsx($book))->save($path);

        return $path;
    }
}
