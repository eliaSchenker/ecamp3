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

    public function getUpcomingEvents(array $roleTypes): array {
        if ([] === $roleTypes) {
            return [];
        }

        // Fetch events from Hitobito. Note that filtering participation does not exclude the event entirely, it only
        // excludes the participation from being listed in its relationships.
        //
        // There is a filter option to directly remove participations with certain roles (filter[participations.roles.type][eq]=...)
        // However this currently results in an error on the Hitobito-side, so the filtering is performed manually in extractEvents.
        $response = $this->httpClient->request('GET', 'events', [
            'query' => [
                // Include the participations of the current user, along with their roles
                'include' => 'participations.roles',
                'filter' => [
                    // Only include currently running events or events in the future
                    'after_or_on' => ['eq' => (new \DateTimeImmutable('today'))->format('Y-m-d')],
                    // Only include participation relationships for the current authenticated user
                    'participations.participant_id' => ['eq' => $this->hitobitoUserId],
                    // Only include participation relationships of the type person
                    'participations.participant_type' => ['eq' => 'Person'],
                ],
                'fields' => [
                    'events' => 'name',
                    'event_participations' => 'event_id,active',
                    'event_roles' => 'participation_id,type',
                ],
                // Maximum number of events returned. Since Hitobito cannot filter events by participation (see above)
                // this number needs to be large enough so that enough relevant events are included. Multiple paginated
                // requests are possible, however the eCamp request timeout must not be exceeded.
                'page' => ['size' => 100],
            ],
        ])->toArray();

        return $this->extractEvents($response, $roleTypes);
    }

    /**
     * @return EventParticipation[]
     */
    public function getEventParticipations(string $eventId): array {
        $response = $this->httpClient->request('GET', 'event_participations', [
            'query' => [
                // include role entities
                'include' => 'roles',
                'filter' => [
                    'participant_id' => ['eq' => $this->hitobitoUserId],
                    'event_id' => ['eq' => $eventId],
                ],
                'fields' => [
                    'event_participations' => 'active',
                    'event_roles' => 'type',
                ],
            ],
        ])->toArray();

        // Extract role types
        $roleTypesById = [];
        foreach ($response['included'] ?? [] as $included) {
            if ('event_roles' === $included['type']) {
                $roleTypesById[$included['id']] = $included['attributes']['type'];
            }
        }

        // Add event participation containing id, active (true/false), the event id and the roles of this user
        $participations = [];
        foreach ($response['data'] as $participation) {
            $roleTypes = [];
            foreach ($participation['relationships']['roles']['data'] ?? [] as $role) {
                if (isset($roleTypesById[$role['id']])) {
                    $roleTypes[] = $roleTypesById[$role['id']];
                }
            }

            $participations[] = new EventParticipation(
                $participation['id'],
                $participation['attributes']['active'],
                $eventId,
                $roleTypes,
            );
        }

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

    /**
     * Extracts relevant events (where the user is leader) from the /events response by checking included
     * participant relationships.
     *
     * @param string[] $roleTypes
     *
     * @return Event[]
     */
    private function extractEvents(array $response, array $roleTypes): array {
        // Gather all included participations
        $participationIdsWithRole = [];
        $participations = [];
        foreach ($response['included'] ?? [] as $included) {
            if ('event_roles' === $included['type']) {
                if (in_array($included['attributes']['type'], $roleTypes, true)) {
                    $participationIdsWithRole[$included['attributes']['participation_id']] = true;
                }
            } elseif ('event_participations' === $included['type']) {
                $participations[] = $included;
            }
        }

        // Determine the events the current user actively participates in with one of the given roles
        $eventIdsWithRole = [];
        foreach ($participations as $participation) {
            if (!$participation['attributes']['active'] || !isset($participationIdsWithRole[$participation['id']])) {
                continue;
            }

            $eventIdsWithRole[$participation['attributes']['event_id']] = true;
        }

        // Extract event data
        $events = [];
        foreach ($response['data'] as $event) {
            if (!isset($eventIdsWithRole[$event['id']])) {
                continue;
            }

            $events[] = new Event($event['id'], $event['attributes']['name'], null, null, []);
        }

        return $events;
    }
}
