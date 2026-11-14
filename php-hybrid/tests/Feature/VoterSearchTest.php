<?php
namespace Tests\Feature;

use App\Models\Document;
use App\Models\User;
use App\Models\VoterRecord;
use Firebase\JWT\JWT;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VoterSearchTest extends TestCase
{
    use RefreshDatabase;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        config(['voter_auth.jwt_secret'=>'phase6-test-secret-0123456789abcdef']);
        $admin = User::query()->create([
            'username'=>'phase6-admin',
            'password'=>bcrypt('password-123456'),
            'role'=>'admin',
            'is_active'=>true,
            'created_at'=>now()->toIso8601String(),
        ]);
        $this->token = JWT::encode([
            'iat'=>time(),
            'exp'=>time()+28800,
            'sub'=>(string)$admin->id,
            'username'=>$admin->username,
            'role'=>'admin',
        ], 'phase6-test-secret-0123456789abcdef', 'HS256');
    }

    private function record(array $overrides=[]): VoterRecord
    {
        return VoterRecord::query()->create(array_merge([
            'voter_id'=>'1234567890',
            'serial_no'=>'0001',
            'name'=>'মোঃ আব্দুল করিম',
            'father_name'=>'মোঃ রহিম উদ্দিন',
            'mother_name'=>'আমেনা বেগম',
            'birth_date'=>'1985-04-12',
            'gender'=>'পুরুষ',
            'occupation'=>'কৃষক',
            'address'=>'গ্রাম: বালিয়া',
            'village'=>'বালিয়া',
            'ward'=>'৫',
            'union_name'=>'বালিয়া ইউনিয়ন',
            'upazila'=>'ত্রিশাল',
            'district'=>'ময়মনসিংহ',
            'division'=>'ময়মনসিংহ',
            'post_code'=>'2220',
            'pdf_filename'=>'mymensingh-voters.pdf',
            'page_number'=>7,
            'raw_text'=>'বাংলা মূল টেক্সট',
            'confidence'=>0.98,
            'created_at'=>now()->toIso8601String(),
        ], $overrides));
    }

    public function test_global_partial_search_finds_bengali_name_and_returns_pdf_source(): void
    {
        $this->record();
        $this->record(['voter_id'=>'9876543210','name'=>'ফাতেমা খাতুন','page_number'=>8]);

        $this->withToken($this->token)->getJson('/api/admin/records?q=আব্দুল')
            ->assertOk()
            ->assertJsonPath('total_count',1)
            ->assertJsonPath('records.0.name','মোঃ আব্দুল করিম')
            ->assertJsonPath('records.0.pdf_filename','mymensingh-voters.pdf')
            ->assertJsonPath('records.0.page_number',7);
    }

    public function test_combined_filters_and_pagination_are_applied(): void
    {
        $this->record();
        $this->record(['voter_id'=>'9876543210','name'=>'আব্দুল মান্নান','ward'=>'৬']);
        $this->record(['voter_id'=>'1111111111','name'=>'আব্দুল হক','district'=>'ঢাকা']);

        $this->withToken($this->token)->getJson('/api/admin/records?q=আব্দুল&district=ময়মনসিংহ&ward=৫&page=1&page_size=1')
            ->assertOk()
            ->assertJsonPath('total_count',1)
            ->assertJsonPath('page',1)
            ->assertJsonPath('page_size',1)
            ->assertJsonPath('total_pages',1);
    }

    public function test_voter_id_and_parent_name_filters_work(): void
    {
        $this->record();
        $this->record(['voter_id'=>'9876543210','father_name'=>'আবুল কালাম']);

        $this->withToken($this->token)->getJson('/api/admin/records?voter_id=123456&father_name=রহিম')
            ->assertOk()->assertJsonPath('total_count',1);
    }

    public function test_csv_export_keeps_bengali_utf8_and_only_exports_filtered_rows(): void
    {
        $this->record();
        $this->record(['voter_id'=>'9876543210','name'=>'অন্য ব্যক্তি']);

        $response = $this->withToken($this->token)->get('/api/admin/export/csv?q=আব্দুল');
        $response->assertOk()->assertHeader('content-type','text/csv; charset=UTF-8');
        $this->assertStringContainsString('মোঃ আব্দুল করিম', $response->streamedContent());
        $this->assertStringNotContainsString('অন্য ব্যক্তি', $response->streamedContent());
    }

    public function test_excel_export_returns_excel_compatible_xml(): void
    {
        $this->record();
        $response = $this->withToken($this->token)->get('/api/admin/export/excel');
        $response->assertOk()->assertHeader('content-type','application/vnd.ms-excel; charset=UTF-8');
        $this->assertStringContainsString('Workbook', $response->streamedContent());
        $this->assertStringContainsString('মোঃ আব্দুল করিম', $response->streamedContent());
    }

    public function test_dashboard_returns_legacy_statistic_keys(): void
    {
        $this->record();
        $this->record(['voter_id'=>'9876543210','district'=>'ঢাকা','gender'=>'মহিলা','occupation'=>'শিক্ষক']);

        $this->withToken($this->token)->getJson('/api/admin/dashboard')
            ->assertOk()
            ->assertJsonPath('total_records',2)
            ->assertJsonPath('total_pdfs',0)
            ->assertJsonStructure(['top_districts','top_occupations','gender_breakdown']);
    }

    public function test_administrative_filters_gender_page_and_legacy_alias_work(): void
    {
        $this->record();
        $this->record([
            'voter_id'=>'9876543210',
            'name'=>'রওশন আরা',
            'gender'=>'মহিলা',
            'upazila'=>'ভালুকা',
            'union_name'=>'ভরাডোবা',
            'ward'=>'২',
            'page_number'=>9,
        ]);

        $this->withToken($this->token)->getJson('/api/voter-search/records?upazila=ত্রিশাল&union=বালিয়া&gender=পুরুষ&page_number=7')
            ->assertOk()
            ->assertJsonPath('total_count',1)
            ->assertJsonPath('records.0.voter_id','1234567890');
    }

    public function test_search_filter_validation_rejects_invalid_pagination_and_oversized_query(): void
    {
        $this->withToken($this->token)->getJson('/api/admin/records?page_size=999')
            ->assertUnprocessable();
        $this->withToken($this->token)->getJson('/api/admin/records?q='.str_repeat('ক',301))
            ->assertUnprocessable();
    }

    public function test_csv_export_neutralizes_spreadsheet_formulas(): void
    {
        $this->record(['name'=>'=HYPERLINK("https://bad.example","click")']);
        $response=$this->withToken($this->token)->get('/api/admin/export/csv');
        $response->assertOk();
        $this->assertStringContainsString("'=HYPERLINK", $response->streamedContent());
    }

    public function test_legacy_alias_dashboard_and_exports_are_protected_and_available(): void
    {
        $this->record();
        $this->withToken($this->token)->getJson('/api/voter-search/dashboard')
            ->assertOk()->assertJsonPath('total_records',1);
        $this->withToken($this->token)->get('/api/voter-search/export/csv')->assertOk();
        $this->withToken($this->token)->get('/api/voter-search/export/excel')->assertOk();
    }

    public function test_search_requires_admin_authentication(): void
    {
        $this->getJson('/api/admin/records')->assertUnauthorized();
        $this->getJson('/api/voter-search/records')->assertUnauthorized();
    }
}
