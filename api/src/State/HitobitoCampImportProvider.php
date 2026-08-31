<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\Camp;
use App\Entity\Period;
use App\Repository\CampRepository;
use App\Service\Hitobito\ClientProvider;
use App\Service\Hitobito\Event;
use App\Service\Hitobito\EventAccessChecker;
use App\Service\Hitobito\HitobitoProvider;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @template-implements ProviderInterface<Camp>
 */
class HitobitoCampImportProvider implements ProviderInterface {
    // Hitobito allows longer values than eCamp for these fields, so they are truncated to the eCamp limits
    private const int TITLE_MAX_LENGTH = 32;
    private const int MOTTO_MAX_LENGTH = 128;
    private const int ADDRESS_NAME_MAX_LENGTH = 128;
    private const int PERIOD_DESCRIPTION_MAX_LENGTH = 32;

    public function __construct(
        private readonly ClientProvider $clientProvider,
        private readonly EventAccessChecker $eventAccessChecker,
        private readonly CampRepository $campRepository,
    ) {}

    /**
     * @throws \DateMalformedStringException
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): Camp {
        $provider = HitobitoProvider::parse($uriVariables['provider']);
        $eventId = $uriVariables['id'];

        $client = $this->clientProvider->getClientForCurrentUser($provider);
        $this->eventAccessChecker->checkAccess($provider, $client, $eventId);

        // Confirm no existing camp exists for this provider/event
        $existingCamp = $this->campRepository->findOneBy(['hitobitoProvider' => $provider, 'hitobitoEventId' => $eventId]);
        if (null !== $existingCamp) {
            throw new ConflictHttpException("Event \"{$eventId}\" was already imported");
        }

        $event = $client->getEvent($eventId);
        if (null === $event) {
            throw new NotFoundHttpException("Event \"{$eventId}\" not found");
        }

        return $this->toCamp($provider, $event);
    }

    /**
     * Converts the given event to a eCamp camp.
     *
     * @throws \DateMalformedStringException
     */
    private function toCamp(HitobitoProvider $provider, Event $event): Camp {
        $camp = new Camp();
        $camp->hitobitoProvider = $provider;
        $camp->hitobitoEventId = $event->id;
        $camp->title = self::truncate($event->name, self::TITLE_MAX_LENGTH) ?? '';
        $camp->motto = self::truncate($event->motto, self::MOTTO_MAX_LENGTH);
        $camp->addressName = self::truncate($event->location, self::ADDRESS_NAME_MAX_LENGTH);

        foreach ($event->dates as $date) {
            $period = new Period();
            // Period description may be null at Hitobito, if so use the event title instead
            $period->description = self::truncate($date->label, self::PERIOD_DESCRIPTION_MAX_LENGTH) ?? $camp->title;
            $period->start = self::toDate($date->startAt);
            // Period end date may be null at Hitobito, if so set the finish date to the start date
            $period->end = self::toDate($date->finishAt ?? $date->startAt);
            $camp->addPeriod($period);
        }

        return $camp;
    }

    /**
     * Truncate the given value to a specified length.
     * If the value is null, null is returned.
     */
    private static function truncate(?string $value, int $maxLength): ?string {
        if (null === $value) {
            return null;
        }

        $value = trim(mb_substr($value, 0, $maxLength));

        return '' === $value ? null : $value;
    }

    /**
     * Truncates the given datetime to a date (without the time component)
     * Hitobito provides us with a full date, whereas eCamp only uses dates for its camp periods.
     *
     * @throws \DateMalformedStringException
     */
    private static function toDate(string $dateTime): \DateTime {
        return new \DateTime(new \DateTimeImmutable($dateTime)->format('Y-m-d'));
    }
}
