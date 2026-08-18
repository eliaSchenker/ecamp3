<?php

namespace App\Tests\Api\Hitobito;

use App\Tests\Api\ECampApiTestCase;

/**
 * @internal
 */
class ListEventsTest extends ECampApiTestCase {
    public function testGetEventIsDeniedForAnonymousUser() {
        static::createBasicClient()->request('GET', '/hitobito/pbsmidata/events');

        $this->assertResponseStatusCodeSame(401);
        $this->assertJsonContains([
            'code' => 401,
            'message' => 'JWT Token not found',
        ]);
    }

    public function testGetEventReturns404ForUnknownProvider() {
        static::createClientWithCredentials()->request('GET', '/hitobito/unknownprovider/events');
        $this->assertResponseStatusCodeSame(404);
    }

    public function testGetEventReturns403WithoutAccessTokenCookie() {
        static::createClientWithCredentials()->request('GET', '/hitobito/pbsmidata/events');

        $this->assertResponseStatusCodeSame(403);
        $this->assertJsonContains(['type' => '/errors/hitobito-access-token-invalid']);
    }

    public function testListEventsReturnsOnlyEventsWhereUserIsLeader() {
        $client = static::createClientWithCredentials();
        static::addHitobitoAccessTokenCookie($client);

        $client->request('GET', '/hitobito/pbsmidata/events');

        $this->assertResponseStatusCodeSame(200);
        $this->assertJsonContains(['totalItems' => 1]);

        $this->assertJsonContains([
            '_embedded' => [
                'items' => [
                    [
                        'id' => 123,
                        'name' => 'Testlager',
                    ],
                ],
            ],
        ]);
    }
}
