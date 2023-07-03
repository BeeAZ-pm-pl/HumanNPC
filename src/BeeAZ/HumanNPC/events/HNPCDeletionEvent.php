<?php

declare(strict_types=1);

namespace BeeAZ\HumanNPC\events;

use pocketmine\entity\Entity;
use pocketmine\event\entity\EntityEvent;
use pocketmine\player\Player;

class HNPCDeletionEvent extends EntityEvent {
    private ?Player $deletetor;

    public function __construct(Entity $entity, Player $deletor = null) {
        $this->entity = $entity;
        $this->deletetor = $deletor;
    }

    public function getDeletor(): ?Player {
        return $this->deletetor;
    }
}