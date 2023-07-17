<?php

declare(strict_types=1);

namespace BeeAZ\HumanNPC\events;

use pocketmine\entity\Entity;
use pocketmine\event\entity\EntityEvent;
use pocketmine\player\Player;

class HumanCreationEvent extends EntityEvent {
    private Player $creator;
    private Entity $entity;

    public function __construct(Entity $entity, Player $creator) {
        $this->entity = $entity;
        $this->creator = $creator;
    }

    public function getCreator(): Player {
        return $this->creator;
    }
}
