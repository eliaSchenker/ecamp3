<?php

namespace App\Tests\State;

use ApiPlatform\Metadata\Post;
use App\Entity\Camp;
use App\Repository\CampRepository;
use App\Service\Hitobito\ClientInterface;
use App\Service\Hitobito\ClientProvider;
use App\Service\Hitobito\Event;
use App\Service\Hitobito\EventAccessChecker;
use App\Service\Hitobito\EventDate;
use App\Service\Hitobito\EventParticipation;
use App\Service\Hitobito\HitobitoProvider;
use App\State\HitobitoCampImportProvider;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @internal
 */
class HitobitoCampImportProviderTest extends TestCase {
    private const array URI_VARIABLES = ['provider' => 'pbsmidata', 'eventId' => '123'];

    private ClientInterface|Stub $client;
    private HitobitoCampImportProvider $provider;

    /** @var EventParticipation[] */
    private array $participations;

    private ?Camp $existingCamp = null;

    protected function setUp(): void {
        $this->participations = [new EventParticipation('1', true, '123', ['Event::Camp::Role::Leader'])];

        $this->client = $this->createStub(ClientInterface::class);
        $this->client->method('getEventParticipations')->willReturnCallback(fn () => $this->participations);

        $clientProvider = $this->createStub(ClientProvider::class);
        $clientProvider->method('getClientForCurrentUser')->willReturn($this->client);

        $campRepository = $this->createStub(CampRepository::class);
        $campRepository->method('findOneBy')->willReturnCallback(fn () => $this->existingCamp);

        $this->provider = new HitobitoCampImportProvider(
            $clientProvider,
            new EventAccessChecker(),
            $campRepository,
        );
    }

    public function testMapsEventToCamp() {
        $this->givenEvent(
            'Testlager',
            'Testmotto',
            'Testort',
            [
                new EventDate('Vorlager', '2026-01-01T00:00:00+01:00', '2026-01-02T00:00:00+01:00'),
                new EventDate('Hauptlager', '2026-01-03T00:00:00+01:00', '2026-01-08T00:00:00+01:00'),
            ],
        );

        $camp = $this->provide();

        $this->assertEquals(HitobitoProvider::PBSMIDATA, $camp->hitobitoProvider);
        $this->assertEquals('123', $camp->hitobitoEventId);
        $this->assertEquals('Testlager', $camp->title);
        $this->assertEquals('Testmotto', $camp->motto);
        $this->assertEquals('Testort', $camp->addressName);
        $this->assertCount(2, $camp->periods);
        $this->assertEquals('Vorlager', $camp->periods[0]->description);
        $this->assertEquals('2026-01-01', $camp->periods[0]->start->format('Y-m-d'));
        $this->assertEquals('2026-01-02', $camp->periods[0]->end->format('Y-m-d'));
        $this->assertEquals('Hauptlager', $camp->periods[1]->description);
        $this->assertEquals('2026-01-03', $camp->periods[1]->start->format('Y-m-d'));
        $this->assertEquals('2026-01-08', $camp->periods[1]->end->format('Y-m-d'));
    }

    public function testTruncatesFieldsExceedingTheECampLimits() {
        $this->givenEvent(
            str_repeat('a', 40),
            str_repeat('b', 140),
            str_repeat('c', 140),
            [new EventDate(str_repeat('d', 40), '2026-01-01T00:00:00+01:00', '2026-01-08T00:00:00+01:00')],
        );

        $camp = $this->provide();

        $this->assertEquals(str_repeat('a', 32), $camp->title);
        $this->assertEquals(str_repeat('b', 128), $camp->motto);
        $this->assertEquals(str_repeat('c', 128), $camp->addressName);
        $this->assertEquals(str_repeat('d', 32), $camp->periods[0]->description);
    }

    public function testUsesCampTitleForEventDatesWithoutLabel() {
        $this->givenEvent('Testlager', null, null, [
            new EventDate(null, '2026-01-01T00:00:00+01:00', '2026-01-08T00:00:00+01:00'),
        ]);

        $camp = $this->provide();

        $this->assertEquals('Testlager', $camp->periods[0]->description);
    }

    public function testCreatesSingleDayPeriodForEventDatesWithoutEnd() {
        $this->givenEvent('Testlager', null, null, [
            new EventDate('Hauptlager', '2026-01-01T00:00:00+01:00', null),
        ]);

        $camp = $this->provide();

        $this->assertEquals('2026-01-01', $camp->periods[0]->start->format('Y-m-d'));
        $this->assertEquals('2026-01-01', $camp->periods[0]->end->format('Y-m-d'));
    }

    public function testThrowsAccessDeniedWhenUserIsNoLeaderOfTheEvent() {
        $this->participations = [new EventParticipation('1', true, '123', ['Event::Role::Helper'])];
        $this->givenEvent('Testlager', null, null, []);

        $this->expectException(AccessDeniedHttpException::class);

        $this->provide();
    }

    public function testThrowsConflictWhenTheEventWasAlreadyImported() {
        $this->existingCamp = new Camp();
        $this->givenEvent('Testlager', null, null, []);

        $this->expectException(ConflictHttpException::class);

        $this->provide();
    }

    public function testThrowsNotFoundWhenTheEventDoesNotExist() {
        $this->client->method('getEvent')->willReturn(null);

        $this->expectException(NotFoundHttpException::class);

        $this->provide();
    }

    /**
     * @param EventDate[] $dates
     */
    private function givenEvent(string $name, ?string $motto, ?string $location, array $dates): void {
        $this->client->method('getEvent')->willReturn(new Event('123', $name, $motto, $location, $dates));
    }

    private function provide(): Camp {
        return $this->provider->provide(new Post(), self::URI_VARIABLES);
    }
}
