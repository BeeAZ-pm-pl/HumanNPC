<?php

declare(strict_types=1);

namespace HumanNPC\events;

use pocketmine\entity\Entity;
use pocketmine\event\entity\EntityEvent;
use pocketmine\player\Player;

class HumanRemoveEvent extends EntityEvent
{
    private ?Player $remover;

    public function __construct(Entity $entity, ?Player $remover = null)
    {
        $this->entity = $entity;
        $this->remover = $remover;
    }

    public function getDeletor(): ?Player
    {
        return $this->remover;
    }
}
