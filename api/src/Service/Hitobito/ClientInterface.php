<?php

namespace App\Service\Hitobito;

interface ClientInterface {
    /**
     * Returns the participations of the Hitobito user this client is authenticated as,
     * optionally limited to a single event.
     *
     * @return EventParticipation[]
     */
    public function getEventParticipations(?string $eventId = null): array;

    /**
     * Returns the details of an event.
     */
    public function getEvent(string $eventId): ?Event;
}
