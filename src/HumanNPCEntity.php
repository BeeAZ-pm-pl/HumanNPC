<?php

declare(strict_types=1);

namespace beeaz\humannpc;

use pocketmine\command\CommandSender;
use pocketmine\entity\Human;
use pocketmine\item\Item;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\ListTag;
use pocketmine\nbt\tag\StringTag;
use pocketmine\utils\TextFormat;

class HumanNPCEntity extends Human {
    private ListTag $commands;

    public function getName(): string {
        return "HumanNPC";
    }

    public function initEntity(CompoundTag $nbt): void {
        $nbt->getListTag("Commands") !== null ?: $nbt->setTag("Commands", new ListTag([]));
        $commands = $nbt->getListTag("Commands");
        
        if(($oldCommands = $nbt->getTag("commands")) !== null) {
            $commands->push($oldCommands);
            $nbt->removeTag("commands");
        }

        $this->commands = $commands;

        $this->setNameTagAlwaysVisible();
        $this->setNameTagVisible();
        $this->setMaxHealth(1000);

        parent::initEntity($nbt);
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
        $nbt->setTag("Commands", $this->commands);
        return $nbt;
    }

    public function getCommands(): array {
        return $this->commands->getAllValues();
    }

    public function removeCommand(CommandSender $sender, string $cmd): void {
        ($cmd[0] != "/") ?: $cmd = substr($cmd, 1);
        if (in_array($cmd, $this->getCommands())) {
            $this->commands->remove(array_search($cmd, $this->getCommands()));
            $sender->sendMessage(TextFormat::GREEN . 'HumanNPC command removed successfully');
        } else {
            $sender->sendMessage(TextFormat::GREEN . 'That command isn\'t exists');
        }
    }

    public function addCommand(CommandSender $sender, string $cmd): void {
        ($cmd[0] != "/") ?: $cmd = substr($cmd, 1);
        if (!in_array($cmd, $this->getCommands())) {
            $this->commands->push(new StringTag($cmd));
            $sender->sendMessage(TextFormat::GREEN . 'HumanNPC command added successfully');
        } else {
            $sender->sendMessage(TextFormat::GREEN . 'That command already exists');
        }
    }

    public function updateName(CommandSender $sender, string $name): void {
        $this->setNameTag(str_replace("{line}", "\n", TextFormat::colorize($name)));
        $sender->sendMessage(TextFormat::GREEN . 'HumanNPC name updated successfully');
    }

    public function updateTool(CommandSender $sender, Item $item): void {
        $this->getInventory()->setItemInHand($item);
        $sender->sendMessage(TextFormat::GREEN . 'HumanNPC tool updated successfully');
    }
}
