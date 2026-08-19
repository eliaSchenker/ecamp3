<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\DTO\HitobitoEvent;
use App\DTO\HitobitoEventDate;
use App\Service\Hitobito\ClientInterface;
use App\Service\Hitobito\ClientProvider;
use App\Service\Hitobito\Event;
use App\Service\Hitobito\EventAccessChecker;
use App\Service\Hitobito\HitobitoProvider;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @template-implements ProviderInterface<HitobitoEvent>
 */
class HitobitoEventProvider implements ProviderInterface {
    public function __construct(
        private readonly ClientProvider $clientProvider,
        private readonly EventAccessChecker $eventAccessChecker,
    ) {}

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array|HitobitoEvent|null {
        $provider = HitobitoProvider::parse($uriVariables['provider']);

        $client = $this->clientProvider->getClientForCurrentUser($provider);

        if (isset($uriVariables['id'])) {
            return $this->provideItem($provider, $client, $uriVariables['id']);
        }

        return $this->provideCollection($provider, $client);
    }

    /**
     * @return HitobitoEvent[]
     */
    private function provideCollection(HitobitoProvider $provider, ClientInterface $client): array {
        $events = $client->getUpcomingEvents($this->eventAccessChecker->getLeaderRoleTypes($provider));

        return array_map(
            fn (Event $event) => $this->toHitobitoEvent($provider, $event),
            $events
        );
    }

    private function provideItem(HitobitoProvider $provider, ClientInterface $client, string $eventId): HitobitoEvent {
        $this->eventAccessChecker->checkAccess($provider, $client, $eventId);

        $event = $client->getEvent($eventId);
        if (null === $event) {
            throw new NotFoundHttpException("Event \"{$eventId}\" not found");
        }

        return $this->toHitobitoEvent($provider, $event);
    }

    private function toHitobitoEvent(HitobitoProvider $provider, Event $event): HitobitoEvent {
        $hitobitoEvent = new HitobitoEvent($provider->value, $event->id, $event->name, $event->motto, $event->location);

        $hitobitoEvent->dates = array_map(
            static fn ($date) => new HitobitoEventDate($date->label, $date->startAt, $date->finishAt),
            $event->dates
        );

        return $hitobitoEvent;
    }
}
