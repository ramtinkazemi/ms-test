<?php
declare(strict_types=1);

namespace App\Facades;


use Illuminate\Support\Facades\Facade;

/**
 * Search Click Logger Facade
 *
 * @package App\Facades
 * @author Naresh Maharjan <naresh.maharjan@cashrewards.com>
 */
class SearchClickLog extends Facade
{
    /**
     * @inheritdoc
     */
    protected static function getFacadeAccessor()
    {
        return 'SearchClickLog';
    }

}