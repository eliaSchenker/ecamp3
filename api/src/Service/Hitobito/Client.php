<?php

declare(strict_types=1);

namespace App\Service\Hitobito;

use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class Client implements ClientInterface {
    private readonly HttpClientInterface $httpClient;
    private readonly int $hitobitoUserId;

    public function __construct(
        HttpClientInterface $client,
        string $baseUrl,
        AuthContext $authContext
    ) {
        $this->httpClient = $client->withOptions([
            // add a trailing slash, if not given (if no trailing slash is present in base uri, its path will be overwritten upon any request)
            'base_uri' => rtrim($baseUrl, '/').'/',
            'auth_bearer' => $authContext->getAccessToken(),
        ]);
        // the access token belongs to this user, so both always describe the same Hitobito user
        $this->hitobitoUserId = $authContext->getUserId();
    }

    /**
     * @return EventParticipation[]
     */
    public function getEventParticipations(?string $eventId = null): array {
        $filter = ['participant_id' => ['eq' => $this->hitobitoUserId]];
        if (null !== $eventId) {
            $filter['event_id'] = ['eq' => $eventId];
        }

        $participations = [];
        $page = 1;

        // Query all participated events page-by-page until all events have been fetched
        do {
            $response = $this->httpClient->request('GET', 'event_participations', [
                'query' => [
                    // include role and event entities
                    'include' => 'roles,event',
                    'filter' => $filter,
                    'fields' => [
                        'event_participations' => 'active',
                        'events' => 'name',
                        'event_roles' => 'type',
                    ],
                    'page' => ['number' => $page, 'size' => 20],
                ],
            ])->toArray();

            // Extract event names and role types

            $eventNamesById = [];
            $roleTypesById = [];
            foreach ($response['included'] ?? [] as $included) {
                if ('events' === $included['type']) {
                    $eventNamesById[$included['id']] = $included['attributes']['name'];
                } elseif ('event_roles' === $included['type']) {
                    $roleTypesById[$included['id']] = $included['attributes']['type'];
                }
            }

            foreach ($response['data'] as $participation) {
                $participationEventId = $participation['relationships']['event']['data']['id'] ?? null;

                if (null === $participationEventId || !isset($eventNamesById[$participationEventId])) {
                    continue;
                }

                $roleTypes = [];
                foreach ($participation['relationships']['roles']['data'] ?? [] as $role) {
                    if (isset($roleTypesById[$role['id']])) {
                        $roleTypes[] = $roleTypesById[$role['id']];
                    }
                }

                // Add event participation containing id, active (true/false), the event id, event name and the roles of this user

                $participations[] = new EventParticipation(
                    $participation['id'],
                    $participation['attributes']['active'],
                    $participationEventId,
                    $eventNamesById[$participationEventId],
                    $roleTypes,
                );
            }

            ++$page;
            // Stop after 5 pages to avoid issues with the response timeout
            if (5 === $page) {
                break;
            }
        } while (isset($response['links']['next']));

        return $participations;
    }

    public function getEvent(string $eventId): ?Event {
        try {
            $response = $this->httpClient->request('GET', "events/{$eventId}", [
                'query' => ['include' => 'dates'],
            ])->toArray();
        } catch (ClientExceptionInterface $e) {
            // Explicitly handle 404
            if (404 === $e->getResponse()->getStatusCode()) {
                return null;
            }

            // Re-throw any unexpected errors
            throw $e;
        }

        // Map all included date objects
        $dates = [];
        foreach ($response['included'] ?? [] as $included) {
            if ('dates' === $included['type']) {
                $dates[] = new EventDate(
                    $included['attributes']['label'],
                    $included['attributes']['start_at'],
                    $included['attributes']['finish_at'],
                );
            }
        }

        return new Event(
            $response['data']['id'],
            $response['data']['attributes']['name'],
            $response['data']['attributes']['motto'],
            $response['data']['attributes']['location'],
            $dates,
        );
    }
}
