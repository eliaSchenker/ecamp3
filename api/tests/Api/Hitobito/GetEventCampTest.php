<?php

namespace App\Tests\Api\Hitobito;

use App\Entity\Camp;
use App\Repository\CampRepository;
use App\Service\Hitobito\HitobitoProvider;
use App\Service\Hitobito\MockClient;
use App\Tests\Api\ECampApiTestCase;
use Doctrine\ORM\EntityManagerInterface;

/**
 * @internal
 */
class GetEventCampTest extends ECampApiTestCase {
    public function testGetEventCampIsDeniedForAnonymousUser() {
        static::createBasicClient()->request('GET', '/hitobito/pbsmidata/events/'.MockClient::EVENT_ID_LEADER.'/camp');

        $this->assertResponseStatusCodeSame(401);
        $this->assertJsonContains([
            'code' => 401,
            'message' => 'JWT Token not found',
        ]);
    }

    public function testGetEventCampReturns404ForUnknownProvider() {
        static::createClientWithCredentials()->request('GET', '/hitobito/unknownprovider/events/'.MockClient::EVENT_ID_LEADER.'/camp');

        $this->assertResponseStatusCodeSame(404);
    }

    public function testGetEventCampReturns403WithoutAccessTokenCookie() {
        static::createClientWithCredentials()->request('GET', '/hitobito/pbsmidata/events/'.MockClient::EVENT_ID_LEADER.'/camp');

        $this->assertResponseStatusCodeSame(403);
        $this->assertJsonContains(['type' => '/errors/hitobito-access-token-invalid']);
    }

    public function testGetEventCampFailureCampForbidden() {
        $this->linkCampToEvent('campUnrelated');

        static::createClientWithCredentials()->request('GET', '/hitobito/pbsmidata/events/'.MockClient::EVENT_ID_LEADER.'/camp');

        $this->assertResponseStatusCodeSame(403);
        $this->assertJsonContains(['type' => '/errors/hitobito-camp-forbidden']);
    }

    public function testGetEventCampFailureEventForbidden() {
        $client = static::createClientWithCredentials();
        static::addHitobitoAccessTokenCookie($client);

        $client->request('GET', '/hitobito/pbsmidata/events/'.MockClient::EVENT_ID_COLEADER.'/camp');

        $this->assertResponseStatusCodeSame(403);
        $this->assertJsonContains(['type' => '/errors/hitobito-event-forbidden']);
    }

    public function testGetEventCampFailureEventExistsCampNotFound() {
        $client = static::createClientWithCredentials();
        static::addHitobitoAccessTokenCookie($client);

        $client->request('GET', '/hitobito/pbsmidata/events/'.MockClient::EVENT_ID_LEADER.'/camp');

        $this->assertResponseStatusCodeSame(404);
        $this->assertJsonContains(['type' => '/errors/hitobito-camp-not-found']);
    }

    public function testGetEventCampFailureEventNotFound() {
        $client = static::createClientWithCredentials();
        static::addHitobitoAccessTokenCookie($client);

        $client->request('GET', '/hitobito/pbsmidata/events/789/camp');

        $this->assertResponseStatusCodeSame(404);
        $this->assertJsonContains(['type' => '/errors/hitobito-event-not-found']);
    }

    public function testGetEventCampSuccess() {
        $camp = $this->linkCampToEvent('camp1');

        static::createClientWithCredentials()->request('GET', '/hitobito/pbsmidata/events/'.MockClient::EVENT_ID_LEADER.'/camp');

        $this->assertResponseStatusCodeSame(200);
        $this->assertJsonContains([
            '_links' => [
                'self' => ['href' => '/hitobito/pbsmidata/events/'.MockClient::EVENT_ID_LEADER.'/camp'],
                'camp' => ['href' => '/camps/'.$camp->getId()],
            ],
            'id' => (int) MockClient::EVENT_ID_LEADER,
        ]);
    }

    /**
     * Links the given fixture camp to Hitobito.
     */
    private function linkCampToEvent(string $fixtureName): Camp {
        /** @var Camp $camp */
        $camp = static::getFixture($fixtureName);

        /** @var CampRepository $campRepository */
        $campRepository = static::getContainer()->get(CampRepository::class);
        $camp = $campRepository->find($camp->getId());

        $camp->hitobitoProvider = HitobitoProvider::PBSMIDATA;
        $camp->hitobitoEventId = MockClient::EVENT_ID_LEADER;

        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $entityManager->flush();

        return $camp;
    }
}
