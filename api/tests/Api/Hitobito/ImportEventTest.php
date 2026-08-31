<?php

namespace App\Tests\Api\Hitobito;

use App\Entity\Camp;
use App\Service\Hitobito\HitobitoProvider;
use App\Service\Hitobito\MockClient;
use App\Tests\Api\ECampApiTestCase;

/**
 * @internal
 */
class ImportEventTest extends ECampApiTestCase {
    public function testImportEventIsDeniedForAnonymousUser() {
        static::createBasicClient()->request('POST', '/hitobito/pbsmidata/events/'.MockClient::EVENT_ID_LEADER.'/import', ['json' => []]);

        $this->assertResponseStatusCodeSame(401);
        $this->assertJsonContains([
            'code' => 401,
            'message' => 'JWT Token not found',
        ]);
    }

    public function testImportEventReturns404ForUnknownProvider() {
        static::createClientWithCredentials()->request('POST', '/hitobito/unknownprovider/events/'.MockClient::EVENT_ID_LEADER.'/import', ['json' => []]);

        $this->assertResponseStatusCodeSame(404);
    }

    public function testImportEventReturns403WithoutAccessTokenCookie() {
        static::createClientWithCredentials()->request('POST', '/hitobito/pbsmidata/events/'.MockClient::EVENT_ID_LEADER.'/import', ['json' => []]);

        $this->assertResponseStatusCodeSame(403);
        $this->assertJsonContains(['type' => '/errors/hitobito-access-token-invalid']);
    }

    public function testImportEventReturns404ForUnknownEvent() {
        $client = static::createClientWithCredentials();
        static::addHitobitoAccessTokenCookie($client);

        $client->request('POST', '/hitobito/pbsmidata/events/999/import', ['json' => []]);

        $this->assertResponseStatusCodeSame(404);
    }

    public function testImportEventReturns403WhenUserIsNotLeader() {
        $client = static::createClientWithCredentials();
        static::addHitobitoAccessTokenCookie($client);

        $client->request('POST', '/hitobito/pbsmidata/events/'.MockClient::EVENT_ID_COLEADER.'/import', ['json' => []]);

        $this->assertResponseStatusCodeSame(403);
    }

    public function testImportEventCreatesCampFromEvent() {
        $client = static::createClientWithCredentials();
        static::addHitobitoAccessTokenCookie($client);

        $client->request('POST', '/hitobito/pbsmidata/events/'.MockClient::EVENT_ID_LEADER.'/import', ['json' => []]);

        $this->assertResponseStatusCodeSame(201);
        $this->assertJsonContains([
            'title' => 'Testlager',
            'motto' => 'Testmotto',
            'addressName' => 'Testort',
            'hitobitoProvider' => HitobitoProvider::PBSMIDATA->value,
            'hitobitoEventId' => MockClient::EVENT_ID_LEADER,
            '_embedded' => [
                'periods' => [
                    [
                        'description' => 'Hauptlager',
                        'start' => '2026-01-01',
                        'end' => '2026-02-01',
                    ],
                ],
            ],
        ]);
    }

    public function testImportEventSetsCreatorToAuthenticatedUser() {
        $client = static::createClientWithCredentials();
        static::addHitobitoAccessTokenCookie($client);

        $client->request('POST', '/hitobito/pbsmidata/events/'.MockClient::EVENT_ID_LEADER.'/import', ['json' => []]);

        $this->assertResponseStatusCodeSame(201);
        $this->assertJsonContains(['_links' => [
            'creator' => ['href' => '/users/'.static::getFixture('user1manager')->getId()],
        ]]);
    }

    public function testImportEventCopiesFromCampPrototype() {
        /** @var Camp $campPrototype */
        $campPrototype = self::getFixture('campPrototype');

        $client = static::createClientWithCredentials();
        static::addHitobitoAccessTokenCookie($client);

        $response = $client->request('POST', '/hitobito/pbsmidata/events/'.MockClient::EVENT_ID_LEADER.'/import', ['json' => [
            'campPrototype' => $this->getIriFor($campPrototype),
        ]]);

        $this->assertResponseStatusCodeSame(201);

        /** @var Camp $camp */
        $camp = $this->getEntityManager()->getRepository(Camp::class)->find($response->toArray()['id']);
        $this->assertEquals($campPrototype->getId(), $camp->campPrototypeId);
        $this->assertCount(1, $camp->categories);
        $this->assertCount(2, $camp->materialLists);
        $this->assertCount(1, $camp->checklists);
        $this->assertCount(3, $camp->progressLabels);
    }

    public function testImportEventReturns409WhenEventWasAlreadyImported() {
        $client = static::createClientWithCredentials();
        $client->disableReboot();
        static::addHitobitoAccessTokenCookie($client);

        $client->request('POST', '/hitobito/pbsmidata/events/'.MockClient::EVENT_ID_LEADER.'/import', ['json' => []]);
        $this->assertResponseStatusCodeSame(201);

        $client->request('POST', '/hitobito/pbsmidata/events/'.MockClient::EVENT_ID_LEADER.'/import', ['json' => []]);
        $this->assertResponseStatusCodeSame(409);
    }
}
