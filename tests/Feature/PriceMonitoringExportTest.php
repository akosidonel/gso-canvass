<?php

use App\Models\PriceRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

uses(RefreshDatabase::class);

function exportedPriceSheet($response): SimpleXMLElement
{
    expect($response->baseResponse)->toBeInstanceOf(BinaryFileResponse::class);
    $path = $response->baseResponse->getFile()->getPathname();
    $zip = new ZipArchive;
    try {
        expect($zip->open($path))->toBeTrue();
        foreach (['[Content_Types].xml', '_rels/.rels', 'xl/workbook.xml', 'xl/_rels/workbook.xml.rels', 'xl/styles.xml'] as $part) {
            expect(simplexml_load_string($zip->getFromName($part)))->not->toBeFalse();
        }
        $sheet = simplexml_load_string($zip->getFromName('xl/worksheets/sheet1.xml'));
        expect($sheet)->not->toBeFalse();
        $sheet->registerXPathNamespace('s', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');

        return $sheet;
    } finally {
        $zip->close();
        unlink($path);
    }
}

test('Excel export includes every record regardless of pagination or search and preserves cell types', function () {
    for ($index = 1; $index <= 20; $index++) {
        PriceRecord::create([
            'qty' => '2.125', 'unit' => 'pcs', 'particulars' => "Paper & <A4>\nñ ".$index,
            'amount' => $index === 20 ? '0' : '10.20', 'department' => 'GSO',
            'control_number' => '001-2026', 'brand_model' => null,
            'category' => 'Office Supplies', 'store' => '=HYPERLINK("https://example.com")', 'canvasser' => 'Staff',
            'fingerprint' => hash('sha256', (string) Str::uuid()),
        ]);
    }
    $this->actingAs(User::factory()->create(['role' => 'canvasser', 'is_active' => true]));
    $this->get('/price-monitoring?search=unmatched')->assertOk()
        ->assertSee('Export all to Excel')
        ->assertSeeInOrder(['id="search"', 'Export all to Excel'], false)
        ->assertSee(route('price-monitoring.export'), false);
    $response = $this->get('/price-monitoring/export?search=unmatched&page=2')->assertOk()
        ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    expect($response->headers->get('content-disposition'))->toContain('.xlsx');
    $sheet = exportedPriceSheet($response);
    expect($sheet->xpath('//s:sheetData/s:row'))->toHaveCount(21);
    $headers = array_map(fn ($cell) => (string) $cell, $sheet->xpath('//s:row[@r="1"]/s:c/s:is/s:t'));
    expect($headers)->toBe(['Category', 'Qty', 'Unit', 'Particulars', 'Amount', 'Department', 'Control number', 'Brand/Model', 'Store', 'Canvasser']);
    expect((string) $sheet->xpath('//s:c[@r="B2"]/s:v')[0])->toBe('2.125');
    expect((string) $sheet->xpath('//s:c[@r="E2"]/s:v')[0])->toBe('0.00');
    expect((string) $sheet->xpath('//s:c[@r="E3"]/s:v')[0])->toBe('10.20');
    expect((string) $sheet->xpath('//s:c[@r="D2"]/s:is/s:t')[0])->toBe("Paper & <A4>\nñ 20");
    expect((string) $sheet->xpath('//s:c[@r="D21"]/s:is/s:t')[0])->toBe("Paper & <A4>\nñ 1");
    expect((string) $sheet->xpath('//s:c[@r="G2"]')[0]['t'])->toBe('inlineStr');
    expect((string) $sheet->xpath('//s:c[@r="G2"]/s:is/s:t')[0])->toBe('001-2026');
    expect((string) $sheet->xpath('//s:c[@r="H2"]/s:is/s:t')[0])->toBe('');
    expect((string) $sheet->xpath('//s:c[@r="I2"]/s:is/s:t')[0])->toBe('=HYPERLINK("https://example.com")');
    expect((string) $sheet->xpath('//s:c[@r="A2"]/s:is/s:t')[0])->toBe('Office Supplies');
    expect($sheet->xpath('//s:f'))->toBe([]);
    expect((string) $sheet->xpath('//s:autoFilter')[0]['ref'])->toBe('A1:J21');
});

test('all roles that can view prices can download an empty workbook with headers', function (string $role) {
    $this->actingAs(User::factory()->create(['role' => $role, 'is_active' => true]));
    $sheet = exportedPriceSheet($this->get('/price-monitoring/export')->assertOk());
    expect($sheet->xpath('//s:sheetData/s:row'))->toHaveCount(1);
    expect($sheet->xpath('//s:row[@r="1"]/s:c'))->toHaveCount(10);
})->with(['system_admin', 'tl_canvasser', 'canvasser']);

test('guests and inactive users cannot download price data', function () {
    $this->get('/price-monitoring/export')->assertRedirect('/signin');
    $this->actingAs(User::factory()->create(['role' => 'canvasser', 'is_active' => false]))
        ->get('/price-monitoring/export')->assertRedirect('/signin');
});

test('users without view permission cannot download price data', function () {
    $this->actingAs(User::factory()->create(['role' => 'unknown', 'is_active' => true]))
        ->get('/price-monitoring/export')->assertRedirect('/signin');
});
