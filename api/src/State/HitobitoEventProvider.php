<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\DTO\HitobitoEvent;
use App\DTO\HitobitoEventDate;
use App\Entity\User;
use App\Service\Hitobito\AccessTokenProvider;
use App\Service\Hitobito\ClientInterface;
use App\Service\Hitobito\ClientProvider;
use App\Service\Hitobito\Event;
use App\Service\Hitobito\EventAccessChecker;
use App\Service\Hitobito\HitobitoProvider;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @template-implements ProviderInterface<HitobitoEvent>
 */
class HitobitoEventProvider implements ProviderInterface {
    public function __construct(
        private readonly Security $security,
        private readonly RequestStack $requestStack,
        private readonly AccessTokenProvider $accessTokenProvider,
        private readonly ClientProvider $clientProvider,
        private readonly EventAccessChecker $eventAccessChecker,
    ) {}

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array|HitobitoEvent|null {
        $provider = HitobitoProvider::parse($uriVariables['provider']);

        $user = $this->getAuthenticatedUser();
        $authContext = $this->accessTokenProvider->getAccessToken($this->getRequest(), $provider, $user->getId());
        $client = $this->clientProvider->getClient($provider, $authContext->getAccessToken());

        if (isset($uriVariables['id'])) {
            return $this->provideItem($provider, $client, $authContext->getUserId(), $uriVariables['id']);
        }

        return $this->provideCollection($provider, $client, $authContext->getUserId());
    }

    /**
     * @return HitobitoEvent[]
     */
    private function provideCollection(HitobitoProvider $provider, ClientInterface $client, int $hitobitoUserId): array {
        $participations = $client->getEventParticipations($hitobitoUserId);

        $events = [];
        foreach ($participations as $participation) {
            if (!$this->eventAccessChecker->isActiveLeaderParticipation($provider, $participation)) {
                continue;
            }

            $events[] = new HitobitoEvent($provider->value, $participation->eventId, $participation->eventName);
        }

        return $events;
    }

    private function provideItem(HitobitoProvider $provider, ClientInterface $client, int $hitobitoUserId, string $eventId): HitobitoEvent {
        $this->eventAccessChecker->checkAccess($provider, $client, $hitobitoUserId, $eventId);

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

    private function getAuthenticatedUser(): User {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new \LogicException('This operation requires an authenticated eCamp user');
        }

        return $user;
    }

    private function getRequest(): Request {
        $request = $this->requestStack->getCurrentRequest();
        if (null === $request) {
            throw new \LogicException('No current request');
        }

        return $request;
    }
}
