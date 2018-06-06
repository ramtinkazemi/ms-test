<?php
declare(strict_types=1);

namespace App\Services\Logger;


use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * Search Click Logger
 *
 * @package App\Services\Logger
 * @author Naresh Maharjan <nareshmaharjan@cashrewards.com>
 */
class SearchClickLog
{

    /**
     * Search click logger
     *
     * @param array $data
     * @return string
     */
    public function log(array $data)
    {
        $message = $this->getInfo($data);
        $res = Storage::disk('s3')->append($this->getPath(), ($message));
        return $res;
    }

    /**
     * Get log path
     *
     * @return string
     */
    private function getPath(): string
    {
        return env('SEARCH_RESULT_CLICK_LOG_DIR_PATH').'/'. env('S3_SEARCH_RESULT_CLICK_LOG').'-'.date('Y-m-d').'.log';
    }

    /**
     * Data sanitizer
     *
     * @param array $data
     * @return string
     */
    private function getInfo(array $data): string
    {

        $info['timestamp'] = Carbon::now()->format('Y-m-d H:i:s.u');
        if (!empty($data['merchantId'])) {
            $info['merchant_id'] = $data['merchantId'];
        }
        if (!empty($data['merchantName'])) {
            $info['merchant_name'] = $data['merchantName'];
        }
        if (!empty($data['memberId'])) {
            $info['member_id'] = $data['memberId'];
        }
        if (!empty($data['clientId'])) {
            $info['client_id'] = $data['clientId'];
        }
        if (!empty($data['searchTerm'])) {
            $info['search_term'] = $data['searchTerm'];
        }
        if (!empty($data['searchWeight'])) {
            $info['search_weight'] = $data['searchWeight'];
        }
        return json_encode($info);
    }
}