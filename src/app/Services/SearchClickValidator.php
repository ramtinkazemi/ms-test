<?php
declare(strict_types=1);

namespace App\Services;


use Carbon\Carbon;
use Illuminate\Support\Facades\Validator;

/**
 * Search click post data validator
 *
 * @package App\Services
 * @author Naresh Maharjan <naresh.maharjan@cashrewards.com>
 */
class SearchClickValidator
{

    /**
     * Validate data
     *
     * @param array $data
     * @return array
     */
    public function validate(array $data): array
    {
        $sanitizeData = $this->sanitizeData($data);
        $validator = Validator::make($sanitizeData, $this->rules());
        if ($validator->fails()) {
            throw new \UnexpectedValueException(json_encode($validator->errors()->toArray()));
        }
        return $sanitizeData;
    }

    /**
     * Get validation rules
     *
     * @return array
     */
    private function rules(): array
    {
        $rules = [
            'merchantId' => 'required|integer',
            'merchantName' => 'required',
            'searchTerm' => 'required',
            'searchWeight' => 'required|numeric',
            'memberId' => 'nullable|integer',
            'clientId' => 'nullable|integer',
        ];
        return $rules;
    }

    /**
     * Sanitize search lick post data before validation
     *
     * @param array $data
     * @return array
     */
    private function sanitizeData(array $data): array
    {
        $sanitizedData['timestamp'] = Carbon::now()->format('Y-m-d H:i:s.u');
        $sanitizedData['merchantId'] = !empty($data['merchantId']) ? $data['merchantId'] : null;
        $sanitizedData['merchantName'] = !empty($data['merchantName']) ? $data['merchantName'] : null;
        $sanitizedData['searchTerm'] = !empty($data['searchTerm']) ? $data['searchTerm'] : null;
        $sanitizedData['searchWeight'] = !empty($data['searchWeight']) ? $data['searchWeight'] : null;
        $sanitizedData['memberId'] = !empty($data['memberId']) ? $data['memberId'] : null;
        $sanitizedData['clientId'] = !empty($data['clientId']) ? $data['clientId'] : null;

        return $sanitizedData;
    }
}