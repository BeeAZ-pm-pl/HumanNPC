<?php

declare(strict_types=1);

namespace beeaz\humannpc;

use beeaz\humannpc\event\HumanNPCCreationEvent;
use beeaz\humannpc\event\HumanNPCRemoveEvent;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\entity\EntityDataHelper;
use pocketmine\entity\EntityFactory;
use pocketmine\entity\Human;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\player\PlayerMoveEvent;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat;
use pocketmine\world\World;
use pocketmine\entity\Location;
use pocketmine\console\ConsoleCommandSender;
use pocketmine\item\VanillaItems;
use pocketmine\math\Vector2;
use pocketmine\nbt\tag\ListTag;
use pocketmine\network\mcpe\protocol\MovePlayerPacket;

class HumanNPC extends PluginBase implements Listener {
    private array $npcIdGetter = [];
    private array $npcRemover = [];

    protected function onEnable(): void {
        EntityFactory::getInstance()->register(HumanNPCEntity::class, function (World $world, CompoundTag $nbt): HumanNPCEntity {
            return new HumanNPCEntity(EntityDataHelper::parseLocation($nbt, $world), Human::parseSkinNBT($nbt), $nbt);
        }, ['HumanNPC', 'humannpc', 'hnpc']);

        $this->getServer()->getPluginManager()->registerEvents($this, $this);
        $this->getServer()->getAsyncPool()->submitTask(new CheckUpdateTask($this->getDescription()->getName(), $this->getDescription()->getVersion()));
    }

    public function onCommand(CommandSender $sender, Command $command, string $label, array $args): bool {
        switch (strtolower($command->getName())) {
            case "runcommandas":
                if (count($args) < 2) {
                    $sender->sendMessage(TextFormat::colorize("&aUsage: /rca <playerName: string> <command: string>"));
                    return true;
                }

                $player = $this->getServer()->getPlayerExact(array_shift($args));
                if ($player instanceof Player) {
                    $this->getServer()->dispatchCommand($player, trim(implode(" ", $args)));
                } else {
                    $sender->sendMessage(TextFormat::colorize("&aPlayer not found"));
                }
                return true;
            case "humannpc":
                if (!$sender instanceof Player) {
                    $sender->sendMessage("Please use this command in-game.");
                    return true;
                }

                if (!isset($args[0])) {
                    $sender->sendMessage(TextFormat::colorize("&aUsage: /humannpc help"));
                    return true;
                }

                switch ($args[0]) {
                    case 'spawn':
                    case 'create':
                    case 'summon':
                    case 's':
                        if (!isset($args[1])) {
                            $sender->sendMessage(TextFormat::colorize("&aUsage: /humannpc spawn <npcName: string>"));
                            break;
                        }

                        $name = implode(" ", array_slice($args, 1));

                        $nbt = CompoundTag::create()
                            ->setString("Name", $sender->getSkin()->getSkinId())
                            ->setByteArray("Data", $sender->getSkin()->getSkinData())
                            ->setByteArray("CapeData", $sender->getSkin()->getCapeData())
                            ->setString("GeometryName", $sender->getSkin()->getGeometryName())
                            ->setByteArray("GeometryData", $sender->getSkin()->getGeometryData())
                            ->setTag("Commands", new ListTag([]));

                        $entity = new HumanNPCEntity(
                            Location::fromObject($sender->getPosition(), $sender->getWorld()),
                            $sender->getSkin(),
                            $nbt
                        );

                        $entity->setNameTag(str_replace("{line}", "\n", TextFormat::colorize($name)));

                        $event = new HumanNPCCreationEvent($entity, $sender);
                        $event->call();

                        $entity->spawnToAll();

                        $sender->sendMessage(TextFormat::colorize("&aHumanNPC has spawned with id: &e" . $entity->getId()));
                        break;
                    case 'delete':
                    case 'remove':
                    case 'r':
                        if (isset($this->npcRemover[$sender->getName()])) {
                            unset($this->npcRemover[$sender->getName()]);
                            $sender->sendMessage(TextFormat::colorize("&aYou are no longer in NPCRemover mode"));
                        } else {
                            $this->npcRemover[$sender->getName()] = true;
                            $sender->sendMessage(TextFormat::colorize("&aYou are in NPCRemover mode"));
                            $sender->sendMessage(TextFormat::colorize("&aTap a HumanNPC to delete"));
                        }
                        break;
                    case 'id':
                    case 'getid':
                    case 'gid':
                        if (isset($this->npcIdGetter[$sender->getName()])) {
                            unset($this->npcIdGetter[$sender->getName()]);
                            $sender->sendMessage(TextFormat::colorize("&aYou are no longer in NPCIDGetter mode"));
                        } else {
                            $this->npcIdGetter[$sender->getName()] = true;
                            $sender->sendMessage(TextFormat::colorize("&aYou are in NPCIDGetter mode"));
                            $sender->sendMessage(TextFormat::colorize("&aTap on HumanNPC to get its ID"));
                        }
                        break;
                    case 'teleport':
                    case 'tp':
                    case 'goto':
                    case 'tpto':
                        if (!isset($args[1])) {
                            $sender->sendMessage(TextFormat::colorize("&aUsage: /humannpc tp <npcId: int>\n&aUse '/humannpc npcs' to get id and name of all HumanNPCs in all worlds"));
                            break;
                        }

                        $id = (int) $args[1];
                        $entity = $this->getServer()->getWorldManager()->findEntity($id);

                        if ($entity === null && !$entity instanceof HumanNPCEntity) {
                            $sender->sendMessage(TextFormat::colorize("&aHumanNPC id not found"));
                            break;
                        }

                        $sender->teleport($entity->getLocation());
                        $sender->sendMessage(TextFormat::colorize('&aTeleported to HumanNPC ' . $entity->getNameTag() . ' successfully'));
                        break;
                    case 'entity':
                    case 'npcs':
                    case 'getnpcs':
                    case 'gnpc':
                        $sender->sendMessage(TextFormat::colorize("&aList of all HumanNPCs:"));
                        foreach ($this->getServer()->getWorldManager()->getWorlds() as $world) {
                            foreach ($world->getEntities() as $entity) {
                                if ($entity instanceof HumanNPCEntity && !$entity->isClosed()) {
                                    $sender->sendMessage(TextFormat::colorize("&a+ &cHumanNPC: " . $entity->getNameTag() . " - Id: " . $entity->getId()));
                                }
                            }
                        }
                        break;
                    case '?':
                    case 'help':
                        $sender->sendMessage(TextFormat::colorize("&aHumanNPC commands list:"));
                        $sender->sendMessage(TextFormat::colorize("&a+ &c/humannpc spawn: &eCreate HumanNPC"));
                        $sender->sendMessage(TextFormat::colorize("&a+ &c/humannpc delete: &eDelete HumanNPC"));
                        $sender->sendMessage(TextFormat::colorize("&a+ &c/humannpc id: &eGet id of HumanNPC"));
                        $sender->sendMessage(TextFormat::colorize("&a+ &c/humannpc tp: &eTeleport to HumanNPC"));
                        $sender->sendMessage(TextFormat::colorize("&a+ &c/humannpc npcs: &eGet id and name of all HumanNPCs in all worlds"));
                        $sender->sendMessage(TextFormat::colorize("&a+ &c/humannpc edit: &eEdit HumanNPC"));
                        break;
                    case 'edit':
                    case 'e':
                        if (count($args) < 3) {
                            $sender->sendMessage(TextFormat::colorize("&aUsage: /humannpc edit <npcId: int> <addcmd|removecmd|getcmd|rename|settool>"));
                            break;
                        }

                        $id = (int) $args[1];
                        $entity = $this->getServer()->getWorldManager()->findEntity($id);

                        if ($entity === null || !$entity instanceof HumanNPCEntity) {
                            $sender->sendMessage(TextFormat::colorize("&aHumanNPC id not found"));
                            break;
                        }

                        switch ($args[2]) {
                            case 'setcmd':
                            case 'setcommand':
                            case 'command':
                            case 'cmd':
                            case 'acmd':
                            case 'addcmd':
                                if (!isset($args[3])) {
                                    $sender->sendMessage(TextFormat::colorize('&aUsage: /humannpc edit <npcId: int> addcmd <command: string>'));
                                    break;
                                }

                                $cmd = trim(implode(" ", array_slice($args, 3)));
                                $entity->addCommand($sender, $cmd);
                                break;
                            case 'removecommand':
                            case 'removecmd':
                            case 'rcmd':
                                if (!isset($args[3])) {
                                    $sender->sendMessage(TextFormat::colorize('&aUsage: /humannpc edit <npcId: int> removecmd <command: string>'));
                                    break;
                                }

                                $cmd = trim(implode(" ", array_slice($args, 3)));
                                $entity->removeCommand($sender, $cmd);
                                break;
                                break;
                            case 'getcmd':
                            case 'getallcommand':
                            case 'getcommand':
                            case 'gcmd':
                            case 'listcmd':
                            case 'lcmd':
                                $commands = $entity->getCommands();
                                $sender->sendMessage(TextFormat::colorize("&aThat HumanNPC commands list:"));
                                foreach ($commands as $command) {
                                    $sender->sendMessage(TextFormat::colorize("&a+ &c" . $command));
                                }
                                break;
                            case 'name':
                            case 'rename':
                                if (!isset($args[3])) {
                                    $sender->sendMessage(TextFormat::colorize('&aUsage: /humannpc edit <npcId: int> name <npcName: string>'));
                                    break;
                                }

                                $name = trim(implode(" ", array_slice($args, 3)));
                                $entity->updateName($sender, $name);
                                break;
                            case 'settool':
                            case 'tool':
                            case 'addtool':
                            case 'sethand':
                            case 'hand':
                                if ($sender->getInventory()->getItemInHand()->equals(VanillaItems::AIR())) {
                                    $sender->sendMessage(TextFormat::colorize('&aHold an item in your hand'));
                                    break;
                                }

                                $entity->updateTool($sender, $sender->getInventory()->getItemInHand());
                                break;
                            default:
                                $sender->sendMessage(TextFormat::colorize('&aUsage: /humannpc edit <npcId: int> <addcmd|removecmd|getcmd|rename|settool>'));
                                break;
                        }
                        break;
                }
                return true;
        }

        return false;
    }

    public function onEntityDamage(EntityDamageEvent $event): void {
        if ($event instanceof EntityDamageByEntityEvent) {
            $damager = $event->getDamager();
            $entity = $event->getEntity();

            if ($damager instanceof Player and $entity instanceof HumanNPCEntity) {
                $event->cancel();

                if (($commands = $entity->getCommands()) != [] and !isset($this->npcIdGetter[$damager->getName()]) and !isset($this->npcRemover[$damager->getName()])) {
                    foreach ($commands as $command) {
                        $this->getServer()->dispatchCommand(new ConsoleCommandSender($this->getServer(), $this->getServer()->getLanguage()), str_replace('{player}', '"' . $damager->getName() . '"', $command));
                    }
                }

                if (isset($this->npcIdGetter[$damager->getName()])) {
                    $event->cancel();
                    $damager->sendMessage(TextFormat::colorize("&aThat HumanNPC id is: " . $entity->getId()));
                    unset($this->npcIdGetter[$damager->getName()]);
                }

                if (isset($this->npcRemover[$damager->getName()])) {
                    $event->cancel();
                    $ev = new HumanNPCRemoveEvent($entity, $damager);
                    $ev->call();
                    $entity->close();
                    $damager->sendMessage(TextFormat::colorize("&aHumanNPC removed successfully"));
                    unset($this->npcRemover[$damager->getName()]);
                }
            }
        }
    }

    public function onPlayerMove(PlayerMoveEvent $event): void {
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

            if ($entity instanceof HumanNPCEntity) {
                $packet = new MovePlayerPacket();
                $packet->actorRuntimeId = $entity->getId();
                $packet->position = $entity->getPosition()->add(0, $entity->getEyeHeight(), 0);
                $packet->yaw = $yaw;
                $packet->pitch = $pitch;
                $packet->headYaw = $yaw;
                $packet->onGround = $entity->onGround;

                $player->getNetworkSession()->sendDataPacket($packet);
            }
        }
    }
}
