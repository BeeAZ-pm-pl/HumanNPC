<?php

declare(strict_types=1);

namespace BeeAZ\HumanNPC;

use HumanNPC\events\HumanCreationEvent;
use HumanNPC\events\HumanRemoveEvent;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\entity\EntityDataHelper;
use pocketmine\entity\EntityFactory;
use pocketmine\entity\Human;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\player\PlayerMoveEvent;
use pocketmine\nbt\tag\ByteArrayTag;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\StringTag;
use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat;
use pocketmine\world\World;
use pocketmine\entity\Location;
use pocketmine\console\ConsoleCommandSender;
use pocketmine\item\VanillaItems;
use pocketmine\math\Vector2;
use pocketmine\network\mcpe\protocol\MovePlayerPacket;

class Loader extends PluginBase implements Listener {
    private array $id = [];
    private array $remove = [];

    protected function onEnable(): void {
        EntityFactory::getInstance()->register(HumanNPC::class, function (World $world, CompoundTag $nbt): HumanNPC {
            return new HumanNPC(EntityDataHelper::parseLocation($nbt, $world), Human::parseSkinNBT($nbt), $nbt);
        }, ['HumanNPC', 'HumanNPC']);

        $this->getServer()->getPluginManager()->registerEvents($this, $this);
        $this->getServer()->getAsyncPool()->submitTask(new CheckUpdateTask($this->getDescription()->getName(), $this->getDescription()->getVersion()));
    }

    public function onCommand(CommandSender $sender, Command $command, string $label, array $args): bool {
        if ($command->getName() === 'rca') {
            if ($sender->hasPermission('humannpc.rca')) {
                if (count($args) < 2) {
                    $sender->sendMessage(TextFormat::colorize("&a/rca <name> <command>"));
                    return true;
                }

                $player = $this->getServer()->getPlayerExact(array_shift($args));

                if ($player instanceof Player) {
                    $this->getServer()->dispatchCommand($player, trim(implode(" ", $args)));
                    return true;
                }
                $sender->sendMessage(TextFormat::colorize("&aPlayer Not Found"));
            }
            return true;
        }

        if ($command->getName() === 'hnpc') {
            if (!$sender instanceof Player) {
                $sender->sendMessage("Please use this command in-game.");
                return true;
            }

            if (!isset($args[0])) {
                $sender->sendMessage(TextFormat::colorize("&a/hnpc <spawn|remove|id|tp|entity|edit>"));
                return true;
            }

            switch ($args[0]) {
                case 'spawn':
                case 'create':
                    if (!isset($args[1])) {
                        $sender->sendMessage(TextFormat::colorize("&a/hnpc spawn <name>"));
                        break;
                    }

                    $name = implode(" ", array_slice($args, 1));

                    $nbt = CompoundTag::create()
                        ->setTag("Name", new StringTag($sender->getSkin()->getSkinId()))
                        ->setTag("Data", new ByteArrayTag($sender->getSkin()->getSkinData()))
                        ->setTag("CapeData", new ByteArrayTag($sender->getSkin()->getCapeData()))
                        ->setTag("GeometryName", new StringTag($sender->getSkin()->getGeometryName()))
                        ->setTag("GeometryData", new ByteArrayTag($sender->getSkin()->getGeometryData()))
                        ->setString('commands', '');

                    $entity = new HumanNPC(
                        Location::fromObject($sender->getPosition(), $sender->getWorld()),
                        $sender->getSkin(),
                        $nbt
                    );

                    $entity->setNameTag(str_replace("{line}", "\n", TextFormat::colorize($name)));

                    $event = new HumanCreationEvent($entity, $sender);
                    $event->call();

                    $entity->spawnToAll();

                    $sender->sendMessage(TextFormat::colorize("&aHumanNPC has spawned with ID: &e" . $entity->getId()));
                    break;

                case 'delete':
                case 'remove':
                    if (isset($this->remove[$sender->getName()])) {
                        unset($this->remove[$sender->getName()]);
                        $sender->sendMessage(TextFormat::colorize("&aExit HumanNPC delete mode successfully"));
                    } else {
                        $this->remove[$sender->getName()] = true;
                        $sender->sendMessage(TextFormat::colorize("&aTap a HumanNPC to delete"));
                    }
                    break;

                case 'id':
                    if (isset($this->id[$sender->getName()])) {
                        unset($this->id[$sender->getName()]);
                        $sender->sendMessage(TextFormat::colorize("&aExit HumanNPC check id mode successfully"));
                    } else {
                        $this->id[$sender->getName()] = true;
                        $sender->sendMessage(TextFormat::colorize("&aTap a HumanNPC to get its ID"));
                    }
                    break;
                case 'teleport':
                case 'tp':
                case 'goto':
                case 'tpto':
                    if (!isset($args[1])) {
                        $sender->sendMessage(TextFormat::colorize("&aUse /hnpc tp <id>\n&aUse /hnpc entity to get the id and name of all HNPCs in the world loaded"));
                        break;
                    }

                    $id = (int)$args[1];
                    $entity = $this->getServer()->getWorldManager()->findEntity($id);

                    if ($entity === null && !$entity instanceof HumanNPC) {
                        $sender->sendMessage(TextFormat::colorize("&aHumanNPC ID not found"));
                        break;
                    }
                    $sender->teleport($entity->getLocation());
                    $sender->sendMessage(TextFormat::colorize('&aTeleported to HNPC ' . $entity->getNameTag() . ' successfully.'));
                    break;
                case 'entity':
                    $all_entity = '';
                    foreach ($this->getServer()->getWorldManager()->getWorlds() as $world) {
                        foreach ($world->getEntities() as $entity) {
                            if ($entity instanceof HumanNPC) {
                                $id = $entity->getId();
                                $name = $entity->getNameTag();
                                $all_entity .= TextFormat::colorize("&a- &c" . $id . ":" . $name . "\n");
                            }
                        }
                    }
                    $sender->sendMessage(TextFormat::colorize("&aHNPC Entitys: \n" . $all_entity));
                    break;
                case '?':
                case 'help':
                    $sender->sendMessage(TextFormat::colorize("&aHNPC Commands"));
                    $sender->sendMessage(TextFormat::colorize("&a/hnpc spawn : &eCreate NPC"));
                    $sender->sendMessage(TextFormat::colorize("&a/hnpc delete : &eDelete NPC"));
                    $sender->sendMessage(TextFormat::colorize("&a/hnpc id : &eGet id NPC"));
                    $sender->sendMessage(TextFormat::colorize("&a/hnpc tp : &eTeleport to NPC"));
                    $sender->sendMessage(TextFormat::colorize("&a/hnpc entity : &eGet all NPC in the world loaded"));
                    $sender->sendMessage(TextFormat::colorize("&a/hnpc edit : &eEdit NPC"));
                    break;
                case 'edit':
                    if (count($args) < 3) {
                        $sender->sendMessage(TextFormat::colorize("&a/hnpc edit <id> <setcmd|rename|settool|setsize>"));
                        break;
                    }

                    $id = (int)$args[1];
                    $entity = $this->getServer()->getWorldManager()->findEntity($id);

                    if ($entity === null && !$entity instanceof HumanNPC) {
                        $sender->sendMessage(TextFormat::colorize("&aHumanNPC ID not found"));
                        break;
                    }

                    switch ($args[2]) {
                        case 'setcmd':
                        case 'setcommand':
                        case 'command':
                        case 'cmd':
                        case 'addcmd':
                            if (!isset($args[3])) {
                                $sender->sendMessage(TextFormat::colorize('&a/hnpc edit <id> setcmd <command>'));
                                break;
                            }

                            $cmd = trim(implode(" ", array_slice($args, 3)));
                            $entity->updateCommand($sender, $cmd);
                            break;

                        case 'name':
                        case 'rename':
                            if (!isset($args[3])) {
                                $sender->sendMessage(TextFormat::colorize('&a/hnpc edit <id> name <name>'));
                                break;
                            }

                            $name = trim(implode(" ", array_slice($args, 3)));
                            $entity->updateName($sender, $name);
                            break;

                        case 'settool':
                        case 'tool':
                        case 'addtool':
                            if ($sender->getInventory()->getItemInHand()->equals(VanillaItems::AIR())) {
                                $sender->sendMessage(TextFormat::colorize('&aHold an item in your hand'));
                                break;
                            }

                            $entity->updateTool($sender, $sender->getInventory()->getItemInHand());
                            break;

                        default:
                            $sender->sendMessage(TextFormat::colorize('&a/hnpc edit <id> <setcmd|rename|settool|setsize>'));
                            break;
                    }
                    break;
            }

            return true;
        }

        return false;
    }

    public function onClick(EntityDamageEvent $event): void {
        if ($event instanceof EntityDamageByEntityEvent) {
            $damager = $event->getDamager();
            $entity = $event->getEntity();

            if ($damager instanceof Player and $entity instanceof HumanNPC) {
                $event->cancel();

                if ($entity->getCommands() !== '' and !isset($this->id[$damager->getName()]) and !isset($this->remove[$damager->getName()])) {
                    $this->getServer()->dispatchCommand(new ConsoleCommandSender($this->getServer(), $this->getServer()->getLanguage()), str_replace('{player}', '"' . $damager->getName() . '"', $entity->getCommands()));
                }

                if (isset($this->id[$damager->getName()])) {
                    $event->cancel();
                    $damager->sendMessage(TextFormat::colorize('&aEntity ID: ' . $entity->getId()));
                    unset($this->id[$damager->getName()]);
                }

                if (isset($this->remove[$damager->getName()])) {
                    $event->cancel();
                    $ev = new HumanRemoveEvent($entity, $damager);
                    $ev->call();
                    $entity->close();
                    unset($this->remove[$damager->getName()]);
                }
            }
        }
    }

    public function onMove(PlayerMoveEvent $event): void {
        $player = $event->getPlayer();
        $from = $event->getFrom();
        $to = $event->getTo();

        if ($from->distance($to) < 0.1) {
            return;
        }

        $maxDistance = 16;
        foreach ($player->getWorld()->getNearbyEntities($player->getBoundingBox()->expandedCopy($maxDistance, $maxDistance, $maxDistance), $player) as $entity) {
            if ($entity instanceof Player) {
                continue;
            }

            $xdiff = $player->getLocation()->x - $entity->getLocation()->x;
            $zdiff = $player->getLocation()->z - $entity->getLocation()->z;
            $angle = atan2($zdiff, $xdiff);
            $yaw = (($angle * 180) / M_PI) - 90;
            $ydiff = $player->getLocation()->y - $entity->getLocation()->y;
            $v = new Vector2($entity->getLocation()->x, $entity->getLocation()->z);
            $dist = $v->distance(new Vector2($player->getLocation()->x, $player->getLocation()->z));
            $angle = atan2($dist, $ydiff);
            $pitch = (($angle * 180) / M_PI) - 90;

            if ($entity instanceof HumanNPC) {
                $pk = new MovePlayerPacket();
                $pk->actorRuntimeId = $entity->getId();
                $pk->position = $entity->getPosition()->add(0, $entity->getEyeHeight(), 0);
                $pk->yaw = $yaw;
                $pk->pitch = $pitch;
                $pk->headYaw = $yaw;
                $pk->onGround = $entity->onGround;

                $player->getNetworkSession()->sendDataPacket($pk);
            }
        }
    }
}
