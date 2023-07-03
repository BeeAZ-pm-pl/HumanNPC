<?php

declare(strict_types=1);

namespace BeeAZ\HumanNPC;

use pocketmine\player\Player;
use pocketmine\entity\Human;
use pocketmine\entity\EntitySizeInfo;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\utils\TextFormat;

class HumanNPC extends Human{

    private $command = '';

    public function getName() : string{
		return "HumanNPC";
	}

    public function initEntity(CompoundTag $nbt) : void{
        parent::initEntity($nbt);
        $this->command = $nbt->getString('commands');
        $this->setNameTagAlwaysVisible();
        $this->setNameTagVisible();
        $this->setHealth(100000000);
    }

    public function onUpdate(int $currentTick) :bool{
        $this->motion->x = 0;
        $this->motion->y = 0;
        $this->motion->z = 0;
        $this->setNoClientPredictions(true);
        if($this->isOnFire()){
           $this->extinguish();
        }
        return parent::onUpdate($currentTick);
    }
    public function saveNBT(): CompoundTag{
        $nbt = parent::saveNBT();
        $nbt->setString("commands", $this->command);
        return $nbt;
    }

    public function getCommands() {
        return $this->command;
    }

    public function updateCommand($sender, $cmd) {
        $this->command = $cmd;
        $sender->sendMessage(TextFormat::colorize('&aUpdate Command Successfully'));
    }

    public function updateName($sender, $name){
        $this->setNameTag(str_replace("{line}", "\n", TextFormat::colorize($name)));
        $sender->sendMessage(TextFormat::colorize('&aUpdate Name Successfully'));
    }

    public function updateTool($sender, $item){
        $this->getInventory()->setItemInHand($item);
        $sender->sendMessage(TextFormat::colorize('&aUpdate Tool Successfully'));
    }
 }
