<?php

namespace Tests\Unit;

use App\Merchant;
use Illuminate\Database\Eloquent\Collection;
use Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class MerchantTest extends TestCase
{
	/**
	 * @cover Merchant::purifyString()
	 */
	public function testPurifyString()
    {
		$this->assertSame("abc", Merchant::purifyString("abc"));
		$this->assertSame("abc", Merchant::purifyString("abC"));
		$this->assertSame("abc", Merchant::purifyString("The abC"));
		$this->assertSame("abc", Merchant::purifyString("a'bc"));
		$this->assertSame("abc", Merchant::purifyString('a\'bc'));
		$this->assertSame("abc", Merchant::purifyString("ab\"c"));
		$this->assertSame("abc", Merchant::purifyString("a*bc"));
		$this->assertSame("a and b", Merchant::purifyString("a & b"));
		$this->assertSame("a  b", Merchant::purifyString("a <br/> b"));
    }

	/**
	 * @cover Merchant::explode()
	 */
	public function testExplode()
	{
		$this->assertSame(["abc"], Merchant::explode("abc"));
		$this->assertSame(["a","b","c"], Merchant::explode("a,b,c"));
		$this->assertSame(["a","b","c"], Merchant::explode("a b c"));
		$this->assertSame(["a","b","c"], Merchant::explode("a, b ,c"));
	}

	/**
	 * @cover Merchant::compare_keywords()
	 */
	public function testCompare_keywords()
	{
		$this->assertSame(false, Merchant::compare_keywords([], []));

		$this->assertSame(false, Merchant::compare_keywords(["bc"], ["abc"]));
		$this->assertSame(0, Merchant::compare_keywords(["abc"], ["abc"]));
		$this->assertSame(1, Merchant::compare_keywords(["abc"], ["bc"]));

		$this->assertSame(false, Merchant::compare_keywords(["bc"], ["abc", "def"]));
		$this->assertSame(0, Merchant::compare_keywords(["abc"], ["abc", "def"]));
		$this->assertSame(1, Merchant::compare_keywords(["abc"], ["bc", "def"]));

		$this->assertSame(false, Merchant::compare_keywords(["ef"], ["abc", "def"]));
		$this->assertSame(0, Merchant::compare_keywords(["def"], ["abc", "def"]));
		$this->assertSame(1, Merchant::compare_keywords(["def"], ["abc", "ef"]));

		$this->assertSame(false, Merchant::compare_keywords( ["bc", "ef"], ["abc", "def"]));
		$this->assertSame(0, Merchant::compare_keywords(["abc", "def"], ["abc", "def"]));
		$this->assertSame(0, Merchant::compare_keywords(["def", "abc"], ["abc", "def"]));

		$this->assertSame(1, Merchant::compare_keywords(["abc", "def"], ["bca", "ef"]));
		$this->assertSame(0, Merchant::compare_keywords(["abc", "def"], ["bc", "def"]));
	}

	/**
	 * @cover Merchant::calculateWeight()
	 */
	public function testCalculateWeight()
	{
		$merchant = new Merchant();
		$merchant->setAttribute('merchantname', "me'rchant");

		//one keyword
		//$this->assertSame(Merchant::NOTHING, $merchant->calculateWeight("me"));
		//$this->assertSame(Merchant::EXACT, $merchant->calculateWeight("merchant"));
		$this->assertSame(Merchant::START, $merchant->calculateWeight("mer"));
		$this->assertSame(Merchant::PART, $merchant->calculateWeight("chant"));

		$merchant->setAttribute('keywords', "merchant, shop, re'tailer");
		$this->assertSame(Merchant::KEYWORD, $merchant->calculateWeight("retail"));

		$merchant->setAttribute('descriptionshort', 'The gre\'atest merchant');
		$this->assertSame(Merchant::DESCRIPTION, $merchant->calculateWeight('greatest'));
		$merchant->setAttribute('descriptionlong', 'The greatest merchant with additonal in\'formation');
		$this->assertSame(Merchant::DESCRIPTION, $merchant->calculateWeight("info"));
		$this->assertSame(Merchant::NOTHING, $merchant->calculateWeight("shopper"));

		$merchant->setAttribute('merchantname', "me'rchant sh\"op");
		$this->assertSame(Merchant::EXACT, $merchant->calculateWeight("merchant shop"));
		$this->assertSame(Merchant::EXACT, $merchant->calculateWeight("shop merchant"));
		$this->assertSame(Merchant::START, $merchant->calculateWeight("mer shop"));
		$this->assertSame(Merchant::PART, $merchant->calculateWeight("test chant"));
		$this->assertSame(Merchant::PART, $merchant->calculateWeight("test shop"));
        $this->assertSame(Merchant::KEYWORD, $merchant->calculateWeight("null", ['keywords' => 'test, keywords, none']));
        $this->assertSame(Merchant::DESCRIPTION, $merchant->calculateWeight("null", ['descriptionlong' => 'Long description']));
		//$this->assertSame(Merchant::KEYWORD, $merchant->calculateWeight("test tail"));
		//$this->assertSame(Merchant::DESCRIPTION, $merchant->calculateWeight("info test"));
		//$this->assertSame(Merchant::DESCRIPTION, $merchant->calculateWeight("add formation"));

	}

	public function dataForSorting()
    {
        $data = [
            [   '_source' => [],
                '_id' => 1,
                'weight' => 3,
                'clickouts' => 3,
                'merchantname' => 'abc',
                'cashbackrate' => 1,
                'relevance' => 2,
                'client_id' => 'cashrewards.com',
                'commissiontype' => 'Percentage Value',
            ],
            [   '_source' => [],
                '_id' => 2,
                'weight' => 4,
                'weight' => 4,
                'clickouts' => 4,
                'merchantname' => 'bcd',
                'cashbackrate' => 2,
                'relevance' => 3,
                'client_id' => 'cashrewards.com',
                'commissiontype' => 'Percentage Value',
            ],
            [   '_source' => [],
                '_id' => 3,
                'weight' => 4,
                'clickouts' => 2,
                'merchantname' => 'Abd',
                'cashbackrate' => 3,
                'relevance' => 1,
                'client_id' => 'cashrewards.com',
                'commissiontype' => '',
            ],
            [   '_source' => [],
                '_id' => 3,
                'weight' => 5,
                'clickouts' => 5,
                'merchantname' => 'def',
                'cashbackrate' => 4,
                'relevance' => 1,
                'client_id' => 'cashrewards.com',
                'commissiontype' => '',
            ],
            [   '_source' => [],
                '_id' => 3,
                'weight' => 1,
                'clickouts' => 1,
                'merchantname' => 'cde',
                'cashbackrate' => 5,
                'relevance' => 1,
                'client_id' => 'cashrewards.com',
                'commissiontype' => '',
            ],
        ];

        $collection = new Collection();
        foreach ( $data as $row)
        {
            $model = new Merchant();
            $model->setRawAttributes($row);
            $collection->push($model);
        }

        return $collection;
    }


	/**
	 * @cover Merchant::sortByStrategy()
	 */
	public function testSortByStrategy()
	{
        $collection = $this->dataForSorting();


        $res = Merchant::sortByStrategy(['relevance' => null], $collection)->map(function($item) {
            return [$item['weight'], $item['clickouts']];
        })->values()->all();

		$this->assertSame(
		    [[5,5],[4,4],[4,2],[3,3],[1,1]], $res);

        $this->assertSame(
            [[5,5],[4,4],[4,2],[3,3],[1,1]],
            Merchant::sortByStrategy(['relevance'], $collection)->map(function($item) {
                return [$item['weight'], $item['clickouts']];
            })->values()->all());

		$this->assertSame(
		    ['abc','Abd','bcd','cde','def'],
            Merchant::sortByStrategy(['merchantname' => 'asc'], 		$collection)->map(function($item) {
                return $item['merchantname'];
            })->values()->all());

		$this->assertSame(
		    ['def','cde','bcd','Abd','abc'],
            Merchant::sortByStrategy(['merchantname' => 'desc'], 		$collection)->map(function($item) {
                return $item['merchantname'];
            })->values()->all());

		$this->assertSame(
		    [2,1,5,4,3],
            Merchant::sortByStrategy(['cashback' => null], $collection)->map(function($item) {
                return $item['cashbackrate'];
            })->values()->all());

        $this->assertSame(
            [1,2,3,4,5],
            Merchant::sortByStrategy([], $collection)->map(function($item) {
                return $item['cashbackrate'];
            })->values()->all());


        $this->assertSame(
            [[3,5],[2,4],[1,3],[3,2],[3,1]],
            Merchant::sortByStrategy(['clickouts' => 'desc'], $collection)->map(function($item) {
                return [$item['_id'], $item['clickouts']];
            })->values()->all());


	}

	public function testSortByStrategyEmptyParam()
    {
        $collection = $this->dataForSorting();

        $this->assertSame(
            [[1,3],[2,4],[3,2],[3,5],[3,1]],
            Merchant::sortByStrategy( '', $collection)->map(function($item) {
                return [$item['_id'], $item['clickouts']];
            })->values()->all());
    }

    public function testSetVisible()
    {
        $data = file_get_contents(__DIR__ . '/Services/find_result_sample.json');

        $result = json_decode($data, true);

        $options = [
            'fields' => [
                'id',
                'client_id',
                'merchantid'
            ]
        ];

        $search['per_page'] = 3;
        $search['page'] = 0;
        $search['query'] = 'test';
        $find_params['keywords'] = Merchant::purifyString($search['query']);

        $merchantsList = new Collection();

        $hits = $result['hits']['hits'];
        //$info['total'] = $result['hits']['total'];
        //$info['query'] = $query;
        foreach($hits as $hit) {
            $model = new Merchant();
            $model->setRawAttributes($hit['_source']);
            $model->setAttribute('id', $hit['_id']);
            $model->setAttribute('weight', $model->calculateWeight($find_params['keywords']));
            $model->setAttribute('relevance', $hit['_score']);
            $model->setAttribute('client_id', $hit['_source']['domain']);
            if (isset($options['fields'])) {
                $model->setVisible($options['fields']);
            }
            $merchantsList->push($model);
        }

        $response = [
            'summary' => [
                'total' => $result['hits']['total'],
                'count' => count($merchantsList),
                'per_page' => $search['per_page'],
                'page' => $search['page'],
            ],
            'items' => $merchantsList->values()->all(),
            'merchants_ids' => $merchantsList->map(function($item) {return $item['merchantid'];})->toArray()
        ];

        $res = json_decode(json_encode($response), true);
        $this->assertSame([
            "merchantid" => 1001405,
            "id" => '1001405-1001405',
            "client_id" => "cashrewards.com.au"
            ], current($res['items']));
    }

}

