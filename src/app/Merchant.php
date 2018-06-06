<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;

use GuzzleHttp\Psr7;
use GuzzleHttp\Exception\RequestException;

class Merchant extends Model
{
	const EXACT = 25;
	const START = 20;
	const PART = 10;
	const KEYWORD = 5;
	const DESCRIPTION = 1;
	const NOTHING = 0;

    /**
     * The "type" of the auto-incrementing ID.
     *
     * @var string
     */
    protected $keyType = 'string';
    
	protected $visible = [
		'id',
		'client_id',
	    'merchantid',
		'merchantname',
		'websiteurl',
		'status',
		'regularimageurlsecure',
		'smallimageurlsecure',
		'mediumimageurlsecure',
		'descriptionshort',
		'descriptionlong',
		'basicterms',
		'extentedterms',
		'cashbackrate',
		'membercommissionrate',
		'clientcommissionrate',
		'commissiontype',
		'downloadoffer',
		'offercount',
		'keywords',
		'trackinglink',
		'weight',
		'clickouts',
        'search_strategy',
        'baserate',
        'store_url',
	];

	/**
	 * Set sorting order
	 *
	 * @param Array $sort
	 * @param Collection $merchants
     * @param &Array $detail
	 * @return Collection
	 */
	public static function sortByStrategy($sort, Collection $merchants, &$detail = null)
    {
        if (!is_array($sort)) {
            $sort = [$sort];
        }

        $field = key($sort);
        $direction = reset($sort);

        // When $sort = [ fieldname ]
        if (is_integer($field) && !empty($direction) ) {
            $field = $direction;
            $direction = 'desc';    // Default Sort direction
        }

        // Initial Setting
        $detail = [
            'sort' => $field.':'.$direction,
            'items' => $merchants
        ];

        if (empty($field))
        {
            return $merchants;
        }

		switch ($field) {
			case 'relevance':
				$merchants_sort = $merchants->sort(function(Merchant $a, Merchant $b){
					if ($a->getAttribute('weight') == $b->getAttribute('weight'))
						return $b->getAttribute('clickouts')*1 - $a->getAttribute('clickouts')*1;
					else
						return $b->getAttribute('weight')*1 - $a->getAttribute('weight')*1;
				});
				break;
            case 'cashback':
                $merchants_sort = $merchants->sort(function(Merchant $a, Merchant $b){
                    if ($a->getAttribute('commissiontype') == $b->getAttribute('commissiontype'))
                        return $b->getAttribute('cashbackrate')*1 - $a->getAttribute('cashbackrate')*1;
                    else
                        return ($a->getAttribute('commissiontype') == "Percentage Value")?-1:1;
                });
                break;

			default:
                    if($direction == 'asc') {
                        $operation = 'sortBy';
                    }
                    else {
                        // Default Operation is 'desc'
                        $operation = 'sortByDesc';
                    }

                    $merchants_sort = $merchants->$operation(function($item, $key) use ($field) {
                        return isset($item[$field])? mb_strtolower($item[$field]) : null;
                    },SORT_STRING);
		}

		$detail['items'] = $merchants_sort;

        return $merchants_sort;
	}

	/**
	 * Remove special symbols etc.
	 *
	 * @param $text
	 * @return mixed
	 *
	 * # Treat capital letters and lowercase the same
	 * # Ignore “The”
	 * # Ignore “apostrophes”
	 * # Ignore “asterisks”
	 * # Treat “and” “&” as the same
	 */
	public static function purifyString($text) {
		$text = mb_strtolower($text);
		$text = preg_replace("/the\s|[\'\"\*]/", "", $text);
		$text = preg_replace("/&/", "and", $text);
		$text = strip_tags($text);
		return $text;
	}

	/**
	 * Split keywords by: space, comma
	 * @param string $keywords
	 * @return array
	 */
	public static function explode($keywords = '') {
		return preg_split("/(?:\s|,)+/", $keywords);
	}

	/**
	 * Looks for intersection in arrays words
	 * @param array $haystack
	 * @param array $needle
	 * @return bool|int
	 */
	public static function compare_keywords($haystack, $needle) {
		$better_result = false;
		for ($i = 0, $l = count($haystack); $i < $l; $i++) {
			for ($j = 0, $k = count($needle); $j < $k; $j++) {
				$pos = mb_strpos($haystack[$i], $needle[$j]);
				if ($pos !== false) {
					if ($better_result === false) {
						$better_result = $pos;
					} else {
						$better_result = $pos<$better_result?$pos:$better_result;
					}
					if ($better_result == 0)
						return 0;
				}
			}
		}
		return $better_result;
	}

    /**
     * Calculate position weight
     *
     * String is an exact match of the retailers name
     * String is a part match of the retailers name
     * Higher rank if the term is at the start merchant name
     * String is found within the retailers keywords
     * String is found within the retailer's description
     * Last resort search, if nothing for above could be found show trending retailers and a message saying we couldn’t find what you were looking for
     *
     * Parameters are the same
     * @param string $sample
     * @param array $highlights
     * @return int
     */
	public function calculateWeight($sample, $highlights = [])
    {
	    $strategy_test_arr = [
            self::EXACT => 'full match',
            self::START => 'partial match at beginning',
            self::PART => 'partial match',
            self::KEYWORD => 'keyword match',
            self::DESCRIPTION => 'description match',
            self::NOTHING => 'no match'
        ];

        $merchant_name = self::purifyString($this->getAttribute('merchantname'));
	    $merchant_name_array = self::explode($merchant_name);
        $keywords = self::purifyString($this->getAttribute('keywords'));

		$sample_array = self::explode($sample);

        if ( $merchant_name === $sample) {
            $weight = self::EXACT;
        }
        elseif (count($sample_array) == count($merchant_name_array)
            && !array_diff($sample_array,$merchant_name_array)) {
            $weight = self::EXACT;
        }
        elseif (mb_strpos($merchant_name, $sample) === 0) {
            $weight = self::START;
        }
        elseif (mb_strpos($merchant_name, $sample) > 0) {
            $weight = self::PART;
        }
        elseif (mb_strpos($merchant_name_array[0], $sample_array[0]) === 0) {
            $weight = self::START;
        }
        elseif (self::compare_keywords($merchant_name_array, $sample_array) !== false) {
            $weight = self::PART;
        }
        elseif (mb_strpos($keywords, $sample) !== false) {
            $weight = self::KEYWORD;
        }
        elseif (mb_strpos(self::purifyString($this->getAttribute('descriptionshort')), $sample) !== false) {
            $weight = self::DESCRIPTION;
        }
        elseif (mb_strpos(self::purifyString($this->getAttribute('descriptionlong')), $sample) !== false) {
            $weight = self::DESCRIPTION;
        }
        elseif (!empty($highlights['keywords'])){
            $weight = self::KEYWORD;
        }
        elseif (!empty($highlights['descriptionlong'])){
            $weight = self::DESCRIPTION;
        }
        else {
            $weight = self::NOTHING;
        }

        $this->setAttribute('search_strategy', $strategy_test_arr[$weight]);
        $this->setAttribute('weight', $weight);

        return $weight;
	}
}
