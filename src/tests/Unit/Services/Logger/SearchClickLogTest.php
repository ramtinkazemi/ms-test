<?php

namespace Tests\Unit\Services\Logger;

use App\Services\Logger\SearchClickLog;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SearchClickLogTest extends TestCase
{
    /**
     * A basic test example.
     *
     * @return void
     */
    public function testLog()
    {
        Storage::fake('s3');
        $now = Carbon::now();
        Carbon::setTestNow($now);
        $scl = new SearchClickLog();
        $searchLog["merchantId"] = 132;
        $searchLog["merchantName"] = "Test Merchant";
        $searchLog["memberId"] = 232;
        $searchLog["clientId"] = 332;
        $searchLog["searchTerm"] = "test search term";
        $searchLog["searchWeight"] = "65.322";

        $actual = $scl->log($searchLog);

        $path = env('SEARCH_RESULT_CLICK_LOG_DIR_PATH').'/'. env('S3_SEARCH_RESULT_CLICK_LOG').'-'.date('Y-m-d').'.log';
        Storage::disk('s3')->assertExists($path);
        $this->assertTrue($actual);
    }
}
