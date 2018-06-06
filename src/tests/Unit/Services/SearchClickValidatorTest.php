<?php

namespace Tests\Unit\Services;

use App\Services\SearchClickValidator;
use Tests\TestCase;

class SearchClickValidatorTest extends TestCase
{

    public function testValidateWithValidData()
    {
        $searchLog["merchantId"] = rand(1, 100);
        $searchLog["merchantName"] = "Test Merchant";
        $searchLog["memberId"] = rand(100, 200);
        $searchLog["clientId"] = rand(200, 300);
        $searchLog["searchTerm"] = "test search term";
        $searchLog["searchWeight"] = 65.322;

        $validator = new SearchClickValidator();

        $actual = $validator->validate($searchLog);

        $this->assertArrayHasKey('timestamp', $actual);
        $this->assertEquals(65.322, $actual['searchWeight']);
    }

    public function testValidateWithInvalidData()
    {
        $this->expectException(\UnexpectedValueException::class);
        $validator = new SearchClickValidator();
        $validator->validate([]);

    }
}
