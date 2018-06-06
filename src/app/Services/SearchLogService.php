<?php

namespace App\Services;

use App\Repositories\SearchRepository;
use function foo\func;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

use GuzzleHttp\Exception\RequestException;
use Carbon\Carbon;
use App\Repositories\EsSearchLogRepository;

class SearchLogService
{
    public function saveSearchLogAsync($search_log)
    {
        $queue = env('QUEUE_SEARCH_LOG');

        try {
            $res = dispatch((new \App\Jobs\LogSearchInfo($search_log))->onQueue($queue));
        } catch (\Exception $e) {
            Log::error( __METHOD__.": ".$e->getMessage());
        }
        return $res;
    }

    public function saveSearchLog($search_log)
    {
        $this->saveSearchLogToFile($search_log);

        $repo = new EsSearchLogRepository();
        return $repo->index($search_log);
    }

    public function getSearchLogFileDirPath($filename)
    {
        return ( empty(config('app.search_log_dir_path')) ? '' : config('app.search_log_dir_path') . '/' ) . $filename;
    }

    public function saveSearchLogToFile($search_log)
    {
        $fileSystemDriver = env('FILESYSTEM_DRIVER', 'local');

        unset($search_log['end_datetime']);

        $logTime = (empty($search_log['end_datetime'])) ? Carbon::now() : Carbon::parse($search_log['end_datetime']);
        $log_time_str = $logTime->setTimezone('UTC')->format('Y-m-d\TH:i:s\Z');

        $log_file_name = $this->getSearchLogFileDirPath('search-log-' . $log_time_str . '.log');
        $res = Storage::disk($fileSystemDriver)->append($log_file_name, json_encode($search_log));
        return $res;
    }

}
