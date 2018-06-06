<?php

namespace App\Http\Controllers;

use App\Jobs\LogSearchClick;
use App\Repositories\SearchClickRepository;
use App\Services\SearchClickValidator;
use Illuminate\Support\Collection;
use App\Repositories\SearchRepository;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;

use Carbon\Carbon;

use App\Merchant;
use App\Services\SearchService;
use App\Services\SearchLogService;

class SearchController extends Controller
{
    protected $service;
    protected $logService;

    public function __construct(SearchService $service, SearchLogService $logService)
    {
        $this->service = $service;
        $this->logService = $logService;
    }

    /**
     * Index search action
     * @param Request $request
     * @return string
     */
    public function index(Request $request)
    {
        //$start_timestamp = Config::get('app.request_timestamp');
        $start_timestamp = LARAVEL_START;
        //$id = Config::get('app.request_id');

        $domain = $request->get('domain');
        $domainlanguage = $request->get('language');

        $search_log = null;

        //------------------------------------
        $start_datetime = \Carbon\Carbon::createFromFormat('U.u',
            number_format($start_timestamp, 6, '.', ''))->setTimezone('UTC');

        $log_info = [
            'start_datetime' => $start_datetime->format('Y-m-d H:i:s.u'),
            'endpoint' => $request->route()->getActionName(),
            'member_id' => null,
            'client_id' => $domain,
            'server_host_name' => $request->getHost(),
            'end_user_ip' => $request->ip(),
            'full_url' => $request->fullUrl(),
            'search_terms' => $request->get('q'),
            'full_user_agent' => $request->header('User-Agent'),
            'pagination' => null,
        ];

        Log::info('Request ', $log_info);
        //-------------------------------------

        //if (!$request->has('q') || mb_strlen($request->get('q')) < 3)
        //	return response([], 200, ['Content-Type' => 'application/json']);
        //
        if (!$request->has('q') || empty($request->get('q'))) {
            $response = [
                'errors' => [
                    [
                        'status' => 400,
                        'title' => "parameter 'q' is required"
                    ]
                ]
            ];
            return response()->json($response, 400);
        }


        /*
         * Set request default parameters
         */
        $essize = Config::get('app.essize');
        $per_page = (Integer)$request->get('per_page', 10);
        $per_page = $per_page <= $essize ? $per_page : $essize;
        if ($per_page == 0) {
            $per_page = 10;
        }

        // page parameter start from 1
        $page = $request->get('page', 0);
        if ($page < 1) {
            $page = 1;
        }


        $log_info['pagination'] = $per_page . ':' . $page;

        $fields = $request->has('fields') ? explode(",", $request->get('fields', '')) : null;

        $search = [
            'query' => Merchant::purifyString($request->get('q')),
            'domain' => $domain,
            'domainlanguage' => $domainlanguage,
            'per_page' => $per_page,
            'page' => $page,
            'fields' => $fields
        ];

        if (Config::get('app.env') == 'local'
            && $request->headers->has('Cache-Control')
            && $request->headers->get('Cache-Control') == 'no-cache') {
            $options['no-cache'] = true;
        }


        if ($request->has('datetime')) {
            $queryTime = Carbon::parse($request->get('datetime'));
        }
        else {
            $queryTime = Carbon::now();
        }

        // yyyy-MM-dd HH:mm:ss.SSS ZZZZZZZZZZZZZZZZ
        $dateTimeStr = sprintf('%s.%s %s',
                $queryTime->format('Y-m-d H:i:s'),
            round('0.'.$queryTime->micro,3)*1000,
                $queryTime->format('e'));

        $options['query_time'] = $dateTimeStr;


        //$options['sort_strategy'] = ($request->has('sort'))?  mb_strtolower($request->get('sort')) : 'relevance';
        $options['sort'] = $this->parseSortArgument($request->get('sort'));

        //============================
        $response = $this->service->searchRepo($search, $options);
        //============================

        if ($response === null) {
            // process will not be reached here as Error is handled by exception handler.
            $errors = $this->service->getErrors();
            $content = [];
            foreach ($errors AS $error) {
                $status = $error->getCode();
                $content[] = ['status' => $status, 'message' => $error->getMessage()];
            }
            $status = empty($status) ? 500 : $status;

            $errors = [];
            $errors[] = [
                'status' => $status,
                'title' => 'ElasticSearch Error',
            ];

            $errors = array_merge($errors, $content);

            return response()->json(['errors' => $errors], $status);
        } elseif (!$response) {
            return response()->json([], 400);
        }
        //-----------------------
        $end_timestamp = microtime(true);
        $end_datetime = \Carbon\Carbon::createFromFormat('U.u',
            number_format($end_timestamp, 6, '.', ''))->setTimezone('UTC');

        $log_info = array_merge($log_info, [
            'end_datetime' => $end_datetime->format('Y-m-d H:i:s.u'),
            'duration' => $end_timestamp - $start_timestamp,
            'merchant_ids' => $response['merchants_ids'],
            'correlation_id' => null,
        ]);

        $response['profile']['duration'] = $log_info['duration'];
        $response['profile']['base_url'] = Url('/');
        $response['profile']['cleint_ip'] = $request->ip();

        Log::info('Response', $log_info);


        $this->logService->saveSearchLogAsync($log_info);
        //-----------------------
        unset($response['merchants_ids']);

        return response()->json($response, 200);
    }

    /**
     * Get auto complete suggestion
     *
     * @author Naresh Maharjan <naresh.maharjan@cashrewards.com>
     * @param Request $request
     * @param SearchRepository $repository
     * @return \Illuminate\Contracts\Routing\ResponseFactory|\Symfony\Component\HttpFoundation\Response
     */
    public function autoComplete(Request $request)
    {
        $search = $request->input('q') ?? '';
        $data = $this->service->autoComplete($search);
        $message = ["status" => 'success', "messages" => $data];
        return response($message, 200);
    }

    /**
     * Search click log POST api endpoint
     *
     * @author Naresh Maharjan <naresh.maharjan@cashrewards.com>
     * @param Request $request
     * @param SearchClickRepository $searchClickRepository
     * @param SearchClickValidator $searchClickValidator
     * @return \Illuminate\Contracts\Routing\ResponseFactory|\Symfony\Component\HttpFoundation\Response
     */
    public function searchClickLog(Request $request, SearchClickRepository $searchClickRepository, SearchClickValidator $searchClickValidator)
    {
        $message = ["status" => "success", "messages" => ["Accepted" => "Processing request"]];
        $statusCode = 202;
        try {
            $postData = $request->all();
            $data = $searchClickValidator->validate($postData);
            LogSearchClick::dispatch($searchClickRepository, $data);
        } catch (\UnexpectedValueException $e) {
            $messages = $e->getMessage();
            $message['status'] = "error";
            $message['messages'] = $messages;
            $statusCode = 400;
        }
        return response($message, $statusCode);
    }

    public function parseSortArgument($argument)
    {
        $sort = [];
        collect(explode(',', $argument))->filter()->map(function ($item) use (&$sort) {

            $arr = explode(':', $item);
            $sort[$arr[0]] = isset($arr[1]) ? $arr[1] : null;
        });
        return $sort;
    }

}
