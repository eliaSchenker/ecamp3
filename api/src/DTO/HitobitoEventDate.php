<?php

declare(strict_types=1);

namespace App\DTO;

use Symfony\Component\Serializer\Attribute\Groups;

class HitobitoEventDate {
    #[Groups(['details'])]
    public ?string $label = null;

    #[Groups(['details'])]
    public string $startAt;

    #[Groups(['details'])]
    public ?string $finishAt = null;

    public function __construct(?string $label, string $startAt, ?string $finishAt) {
        // these fields may be an empty string at Hitobito, normalize to null
        if ('' != $label) {
            $this->label = $label;
        }
        $this->startAt = $startAt;

        $this->finishAt = $finishAt;
    }
}
