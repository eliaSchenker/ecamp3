<?php

declare(strict_types=1);

namespace App\Service\Hitobito;

/**
 * MockClient returns mocked Hitobito data used for local development and testing.
 */
class MockClient implements ClientInterface {
    public const string EVENT_ID_LEADER = '123';
    public const string EVENT_ID_COLEADER = '456';

    public function getEventParticipations(int $hitobitoUserId, ?string $eventId = null): array {
        $mockParticipations = [
            new EventParticipation(
                '1',
                true,
                self::EVENT_ID_LEADER,
                'Testlager',
                ['Event::Camp::Role::Leader'],
            ),
            new EventParticipation(
                '2',
                true,
                self::EVENT_ID_COLEADER,
                'Testlager 2',
                ['Event::Role::Helper'],
            ),
        ];

        if (null !== $eventId) {
            return array_filter($mockParticipations, function ($v) use ($eventId) {
                return $eventId === $v->eventId;
            });
        }

        return $mockParticipations;
    }

    public function getEvent(string $eventId): ?Event {
        if (self::EVENT_ID_LEADER != $eventId) {
            return null;
        }

        return new Event(
            self::EVENT_ID_LEADER,
            'Testlager',
            'Testmotto',
            'Testort',
            [
                new EventDate(
                    'Hauptlager',
                    '2026-01-01T00:00:00+00:00',
                    '2026-02-01T00:00:00+00:00',
                ),
            ],
        );
    }
}
