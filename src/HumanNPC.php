<?php

declare(strict_types=1);

namespace BeeAZ\HumanNPC;

use pocketmine\entity\Human;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\utils\TextFormat;

class HumanNPC extends Human {
    private string $command = '';

    public function getName(): string {
        return "HumanNPC";
    }

    public function initEntity(CompoundTag $nbt): void {
        parent::initEntity($nbt);

        if (!$nbt->getTag("commands")) {
            $nbt->setString("commands", "");
        }
        $this->command = $nbt->getString('commands');
        $this->setNameTagAlwaysVisible();
        $this->setNameTagVisible();
        $this->setMaxHealth(100000000);
    }

    public function onUpdate(int $currentTick): bool {
        $this->setMotion($this->getMotion()->withComponents(0, 0, 0));
        $this->setGravity(0.0);

        if ($this->isOnFire()) {
            $this->extinguish();
        }

        return parent::onUpdate($currentTick);
    }

    public function saveNBT(): CompoundTag {
        $nbt = parent::saveNBT();
        $nbt->setString("commands", $this->command);
        return $nbt;
    }

    public function getCommands(): string {
        return $this->command;
    }

    public function updateCommand($sender, $cmd): void {
        $this->command = $cmd;
        $sender->sendMessage(TextFormat::GREEN . 'Command updated successfully.');
    }

    public function updateName($sender, $name): void {
        $this->setNameTag(str_replace("{line}", "\n", TextFormat::colorize($name)));
        $sender->sendMessage(TextFormat::GREEN . 'Name updated successfully.');
    }

    public function updateTool($sender, $item): void {
        $this->getInventory()->setItemInHand($item);
        $sender->sendMessage(TextFormat::GREEN . 'Tool updated successfully.');
    }
}
