<?php

declare(strict_types=1);

namespace BeeAZ\HumanNPC;

use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\player\Player;
use pocketmine\entity\{EntityFactory,
    EntityDataHelper,
    Human};
use pocketmine\world\World;
use pocketmine\entity\Location;
use pocketmine\nbt\tag\{ByteArrayTag, 
    CompoundTag, 
    ListTag, 
    StringTag, 
    NameTag};
use pocketmine\command\{Command,
    CommandSender};
use pocketmine\console\ConsoleCommandSender;
use pocketmine\event\entity\{EntityDamageEvent,
    EntityDamageByEntityEvent};
use pocketmine\event\player\PlayerMoveEvent;
use pocketmine\utils\{Config,
    TextFormat};
use pocketmine\item\VanillaItems;
use pocketmine\math\Vector2;
use pocketmine\network\mcpe\protocol\MovePlayerPacket;

class Loader extends PluginBase implements Listener{

    public function onEnable(): void{
        EntityFactory::getInstance()->register(HumanNPC::class, function (World $world, CompoundTag $nbt): HumanNPC {
            return new HumanNPC(EntityDataHelper::parseLocation($nbt, $world), Human::parseSkinNBT($nbt), $nbt);
        }, ['HumanNPC', 'HumanNPC']);
		    $this->getServer()->getPluginManager()->registerEvents($this, $this);
    }

    public function onCommand(CommandSender $sender, Command $cmd, string $label, array $args) : bool{
        if($cmd->getName() === 'rca'){
          if($sender->hasPermission('hnpc.rca')){
          if (count($args) < 2) {
            $sender->sendMessage(TextFormat::colorize("&a/rca <name> <command>"));
            return true;
          }
          $player = $this->getServer()->getPlayerByExact(array_shift($args));
          if($player instanceof Player) {
            $this->getServer()->dispatchCommand($player, trim(implode(" ", $args)));
            return true;
          }
          $sender->sendMessage(TextFormat::colorize("&aPlayer Not Found"));
          }
            return true;
        }
        if($cmd->getName() === 'hnpc'){
           if($sender instanceof Player){
           if(isset($args[0])){
             switch($args[0]){
               case 'spawn':
               case 'create':
               if(isset($args[1])){
                 if(is_string($args[1])){
                   $nbt = CompoundTag::create();
                   $nbt->setTag("Name", new StringTag($sender->getSkin()->getSkinId()));
                   $nbt->setTag("Data", new ByteArrayTag($sender->getSkin()->getSkinData()));
                   $nbt->setTag("CapeData", new ByteArrayTag($sender->getSkin()->getCapeData()));
                   $nbt->setTag("GeometryName", new StringTag($sender->getSkin()->getGeometryName()));
                   $nbt->setTag("GeometryData", new ByteArrayTag($sender->getSkin()->getGeometryData()));
                   $nbt->setString('commands', '');
                   $entity = new HumanNPC(Location::fromObject($sender->getPosition(), $sender->getPosition()->getWorld(), $sender->getLocation()->getYaw() ?? 0, $sender->getLocation()->getPitch() ?? 0), $sender->getSkin(), $nbt);
                   $entity->setNameTag($args[1]);
                   $entity->spawnToAll();
                   $sender->sendMessage(TextFormat::colorize("&aHumanNPC has spawned with ID: &e".$entity->getId()));
                }else $sender->sendMessage(TextFormat::colorize("&aData Correct"));
                }else $sender->sendMessage(TextFormat::colorize("&a/hnpc spawn <name>"));
               break;
               case 'delete':
               case 'remove':
               if(isset($this->remove[$sender->getName()])){
                   unset($this->remove[$sender->getName()]);
                   $sender->sendMessage(TextFormat::colorize("&aExit HumanNPC delete mode success"));
               }else{
                   $this->remove[$sender->getName()] = true;
                   $sender->sendMessage(TextFormat::colorize("&aTap to HumanNPC to delete"));
               }
               break;
               case 'id':
               if(isset($this->id[$sender->getName()])){
                   unset($this->id[$sender->getName()]);
                   $sender->sendMessage(TextFormat::colorize("&aExit HumanNPC check id mode successful"));
               }else{
                   $this->id[$sender->getName()] = true;
                   $sender->sendMessage(TextFormat::colorize("&aTap to HumanNPC to id"));
               }
               break;
               case 'edit':
               if(isset($args[1]) && isset($args[2])){
                  if(is_numeric($args[1])){
                    $id = (int)$args[1];
                    $entity = $this->getServer()->getWorldManager()->findEntity($id);
                  if($entity !== null){
                  switch($args[2]){
                    case 'setcmd':
                    case 'setcommand':
                    case 'command':
                    case 'cmd':
                    case 'addcmd':
                      if(isset($args[3])){
                      if(is_string($args[3])){
                          $entity->updateCommand($sender, $args[3]);
                      }else $sender->sendMessage(TextFormat::colorize('&aData must be type string'));
                    }else $sender->sendMessage(TextFormat::colorize('&a/hnpc edit <id> setcmd <command>'));
                    break;
                    case 'name':
                    case 'rename':
                      if(isset($args[3])){
                        if(is_string($args[3])){
                           $entity->updateName($sender, $args[3]);
                        }else $sender->sendMessage(TextFormat::colorize('&aData must be type string'));
                     }else $sender->sendMessage(TextFormat::colorize('&a/hnpc edit <id> name <name>'));
                    break;
                    case 'settool':
                    case 'tool':
                    case 'addtool':
                      if(!$sender->getInventory()->getItemInHand()->equals(VanillaItems::AIR())){
                           $entity->updateTool($sender, $sender->getInventory()->getItemInHand());
                      }else $sender->sendMessage(TextFormat::colorize('&aHold item your hand'));
                    break;
                  }
               }else $sender->sendMessage(TextFormat::colorize('&aHumanNPC ID not found'));             
               }else $sender->sendMessage(TextFormat::colorize('&aData must be type int'));
               }else $sender->sendMessage(TextFormat::colorize('&a/hnpc edit <id> <setcmd|rename|settool>'));     
              break; 
            }
            }else $sender->sendMessage(TextFormat::colorize('&a/hnpc <spawn|remove|id|edit>'));
        }else $sender->sendMessage('Please use command ingame');
      return true;
    }
}

    public function onClick(EntityDamageEvent $event){
      if($event instanceof EntityDamageByEntityEvent){
         $damager = $event->getDamager();
         $entity = $event->getEntity();
      if($damager instanceof Player){
         if($entity instanceof HumanNPC){
            $event->cancel();
      if($entity->getCommands() !== '' && !isset($this->id[$damager->getName()]) && !isset($this->remove[$damager->getName()])){
            $this->getServer()->dispatchCommand(new ConsoleCommandSender($this->getServer(), $this->getServer()->getLanguage()), str_replace('{player}', '"'.$damager->getName().'"', $entity->getCommands()));
      }
      if(isset($this->id[$damager->getName()])){
           $event->cancel();
           $damager->sendMessage(TextFormat::colorize('&aEntity ID : '.$entity->getId()));
           unset($this->id[$damager->getName()]);
      }
      if(isset($this->remove[$damager->getName()])){
           $event->cancel();
           $entity->close();
           unset($this->remove[$damager->getName()]);
      }
      }
      }
    }
  }
  public function onMove(PlayerMoveEvent $ev) {
		$player = $ev->getPlayer();
		$from = $ev->getFrom();
		$to = $ev->getTo();

		if($from->distance($to) < 0.1) {
			return;
		}
		$maxDistance = 16;
		foreach ($player->getWorld()->getNearbyEntities($player->getBoundingBox()->expandedCopy($maxDistance, $maxDistance, $maxDistance), $player) as $e) {
			if($e instanceof Player){
				continue;
			}
			$xdiff = $player->getLocation()->x - $e->getLocation()->x;
			$zdiff = $player->getLocation()->z - $e->getLocation()->z;
			$angle = atan2($zdiff, $xdiff);
			$yaw = (($angle * 180) / M_PI) - 90;
			$ydiff = $player->getLocation()->y - $e->getLocation()->y;
			$v = new Vector2($e->getLocation()->x, $e->getLocation()->z);
			$dist = $v->distance(new Vector2($player->getLocation()->x, $player->getLocation()->z));
			$angle = atan2($dist, $ydiff);
			$pitch = (($angle * 180) / M_PI) - 90;
			if($e instanceof HumanNPC){
				$pk = new MovePlayerPacket();
				$pk->actorRuntimeId = $e->getId();
        $pk->position = $e->getPosition()->add(0, $e->getEyeHeight(), 0);
        $pk->yaw = $yaw;
        $pk->pitch = $pitch;
        $pk->headYaw = $yaw;
        $pk->onGround = $e->onGround;
				$player->getNetworkSession()->sendDataPacket($pk);
      }
      }
   }
}