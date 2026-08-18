<?php

namespace App\Service\Hitobito;

interface ClientInterface {
    /**
     * @return EventParticipation[]
     */
    public function getEventParticipations(int $hitobitoUserId, ?string $eventId = null): array;

    /**
     * Returns the details of an event.
     */
    public function getEvent(string $eventId): ?Event;
}
