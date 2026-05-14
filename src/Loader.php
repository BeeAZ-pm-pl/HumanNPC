<?php

declare(strict_types=1);

namespace BeeAZ\HumanNPC;

use BeeAZ\HumanNPC\events\HumanCreationEvent;
use BeeAZ\HumanNPC\events\HumanRemoveEvent;
use BeeAZ\HumanNPC\task\FetchSkinTask;
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

class Loader extends PluginBase implements Listener
{

    private array $npcIdGetter = [];
    private array $npcRemover = [];

    protected function onEnable(): void
    {
        EntityFactory::getInstance()->register(HumanNPC::class, function (World $world, CompoundTag $nbt): HumanNPC {
            return new HumanNPC(EntityDataHelper::parseLocation($nbt, $world), Human::parseSkinNBT($nbt), $nbt);
        }, ['HumanNPC', 'humannpc', 'hnpc']);

        $this->getServer()->getPluginManager()->registerEvents($this, $this);
    }

    public function onCommand(CommandSender $sender, Command $command, string $label, array $args): bool
    {
        switch (strtolower($command->getName())) {
            case "runcommandas":
            case "rca":
                if (count($args) < 2) {
                    $sender->sendMessage(TextFormat::colorize("&cUsage: /rca <player> <command>"));
                    return true;
                }

                $player = $this->getServer()->getPlayerExact(array_shift($args));
                if ($player instanceof Player) {
                    $this->getServer()->dispatchCommand($player, trim(implode(" ", $args)));
                } else {
                    $sender->sendMessage(TextFormat::colorize("&cPlayer not found."));
                }
                return true;

            case "humannpc":
            case "hnpc":
                if (!$sender instanceof Player) {
                    $sender->sendMessage("This command can only be used in-game.");
                    return true;
                }

                if (!isset($args[0])) {
                    $sender->sendMessage(TextFormat::colorize("&cUsage: /hnpc help"));
                    return true;
                }

                switch ($args[0]) {
                    case 'spawn':
                    case 'create':
                    case 'summon':
                    case 's':
                        if (!isset($args[1])) {
                            $sender->sendMessage(TextFormat::colorize("&cUsage: /hnpc spawn <name>"));
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

                        $entity = new HumanNPC(
                            Location::fromObject($sender->getPosition(), $sender->getWorld()),
                            $sender->getSkin(),
                            $nbt
                        );

                        $entity->setNameTag(str_replace("{line}", "\n", TextFormat::colorize($name)));

                        $event = new HumanCreationEvent($entity, $sender);
                        $event->call();

                        $entity->spawnToAll();

                        $sender->sendMessage(TextFormat::colorize("&aHumanNPC spawned successfully with ID: &e" . $entity->getId()));
                        break;

                    case 'delete':
                    case 'remove':
                    case 'r':
                        if (isset($this->npcRemover[$sender->getName()])) {
                            unset($this->npcRemover[$sender->getName()]);
                            $sender->sendMessage(TextFormat::colorize("&aYou have exited NPC removal mode."));
                        } else {
                            $this->npcRemover[$sender->getName()] = true;
                            $sender->sendMessage(TextFormat::colorize("&aYou have entered NPC removal mode."));
                            $sender->sendMessage(TextFormat::colorize("&eTap a HumanNPC to delete it."));
                        }
                        break;

                    case 'id':
                    case 'getid':
                    case 'gid':
                        if (isset($this->npcIdGetter[$sender->getName()])) {
                            unset($this->npcIdGetter[$sender->getName()]);
                            $sender->sendMessage(TextFormat::colorize("&aYou have exited NPC ID getter mode."));
                        } else {
                            $this->npcIdGetter[$sender->getName()] = true;
                            $sender->sendMessage(TextFormat::colorize("&aYou have entered NPC ID getter mode."));
                            $sender->sendMessage(TextFormat::colorize("&eTap a HumanNPC to get its ID."));
                        }
                        break;

                    case 'teleport':
                    case 'tp':
                    case 'goto':
                    case 'tpto':
                        if (!isset($args[1])) {
                            $sender->sendMessage(TextFormat::colorize("&cUsage: /hnpc tp <npcId>"));
                            $sender->sendMessage(TextFormat::colorize("&cUse '/hnpc npcs' to get a list of all NPCs."));
                            break;
                        }

                        $id = (int) $args[1];
                        $entity = $this->getServer()->getWorldManager()->findEntity($id);

                        if ($entity === null || !$entity instanceof HumanNPC) {
                            $sender->sendMessage(TextFormat::colorize("&cHumanNPC with the specified ID was not found."));
                            break;
                        }

                        $sender->teleport($entity->getLocation());
                        $sender->sendMessage(TextFormat::colorize("&aTeleported to HumanNPC: &f" . $entity->getNameTag()));
                        break;

                    case 'entity':
                    case 'npcs':
                    case 'getnpcs':
                    case 'gnpc':
                        $sender->sendMessage(TextFormat::colorize("&aList of all active HumanNPCs:"));
                        foreach ($this->getServer()->getWorldManager()->getWorlds() as $world) {
                            foreach ($world->getEntities() as $entity) {
                                if ($entity instanceof HumanNPC && !$entity->isClosed()) {
                                    $sender->sendMessage(TextFormat::colorize("&a+ &f" . $entity->getNameTag() . " &7- ID: &e" . $entity->getId()));
                                }
                            }
                        }
                        break;

                    case '?':
                    case 'help':
                        $sender->sendMessage(TextFormat::colorize("&a--- HumanNPC Advanced Guide ---"));
                        $sender->sendMessage(TextFormat::colorize("&e1. Basic Management:"));
                        $sender->sendMessage(TextFormat::colorize("&f/hnpc spawn <name> &7- Spawn an NPC matching your skin."));
                        $sender->sendMessage(TextFormat::colorize("&f/hnpc id &7- Toggle ID Mode (Hit an NPC to get its ID)."));
                        $sender->sendMessage(TextFormat::colorize("&f/hnpc delete &7- Toggle Delete Mode (Hit an NPC to remove)."));
                        $sender->sendMessage(TextFormat::colorize("&f/hnpc npcs &7- List all NPCs / &f/hnpc tp <id> &7- Teleport to NPC."));
                        $sender->sendMessage(TextFormat::colorize("&e2. Editing Appearance (/hnpc edit <id>):"));
                        $sender->sendMessage(TextFormat::colorize("&f... rename <name> &7- Change the name of the NPC."));
                        $sender->sendMessage(TextFormat::colorize("&f... settool &7- Give the NPC the item you are holding."));
                        $sender->sendMessage(TextFormat::colorize("&f... setskin <url> &7- Update skin via a direct PNG link."));
                        $sender->sendMessage(TextFormat::colorize("&e3. Click Commands (addcmd):"));
                        $sender->sendMessage(TextFormat::colorize("&7Commands run as Console by default. Use &b{player} &7for the clicker's name."));
                        $sender->sendMessage(TextFormat::colorize("&a> Run as System (Console):"));
                        $sender->sendMessage(TextFormat::colorize("&f/hnpc edit <id> addcmd give {player} diamond_sword 1"));
                        $sender->sendMessage(TextFormat::colorize("&a> Force Player to Run Command (Using RCA):"));
                        $sender->sendMessage(TextFormat::colorize("&f/hnpc edit <id> addcmd rca {player} shop"));
                        $sender->sendMessage(TextFormat::colorize("&e4. Managing Commands:"));
                        $sender->sendMessage(TextFormat::colorize("&f/hnpc edit <id> listcmd &7- View all assigned commands."));
                        $sender->sendMessage(TextFormat::colorize("&f/hnpc edit <id> removecmd <command> &7- Remove a specific command."));
                        break;

                    case 'edit':
                    case 'e':
                        if (count($args) < 3) {
                            $sender->sendMessage(TextFormat::colorize("&cUsage: /hnpc edit <npcId> <addcmd|removecmd|listcmd|rename|settool|setskin>"));
                            break;
                        }

                        $id = (int) $args[1];
                        $entity = $this->getServer()->getWorldManager()->findEntity($id);

                        if ($entity === null || !$entity instanceof HumanNPC) {
                            $sender->sendMessage(TextFormat::colorize("&cHumanNPC with the specified ID was not found."));
                            break;
                        }

                        switch ($args[2]) {
                            case 'setskin':
                            case 'skin':
                                if (!isset($args[3])) {
                                    $sender->sendMessage(TextFormat::colorize("&cUsage: /hnpc edit <npcId> setskin <url>"));
                                    break;
                                }

                                $url = trim($args[3]);
                                $sender->sendMessage(TextFormat::colorize("&eDownloading and applying skin..."));
                                $this->getServer()->getAsyncPool()->submitTask(new FetchSkinTask($url, $id, $sender->getName()));
                                break;

                            case 'setcmd':
                            case 'setcommand':
                            case 'command':
                            case 'cmd':
                            case 'acmd':
                            case 'addcmd':
                                if (!isset($args[3])) {
                                    $sender->sendMessage(TextFormat::colorize("&cUsage: /hnpc edit <npcId> addcmd <command>"));
                                    break;
                                }

                                $cmd = trim(implode(" ", array_slice($args, 3)));
                                $entity->addCommand($sender, $cmd);
                                break;

                            case 'removecommand':
                            case 'removecmd':
                            case 'rcmd':
                                if (!isset($args[3])) {
                                    $sender->sendMessage(TextFormat::colorize("&cUsage: /hnpc edit <npcId> removecmd <command>"));
                                    break;
                                }

                                $cmd = trim(implode(" ", array_slice($args, 3)));
                                $entity->removeCommand($sender, $cmd);
                                break;

                            case 'getcmd':
                            case 'getallcommand':
                            case 'getcommand':
                            case 'gcmd':
                            case 'listcmd':
                            case 'lcmd':
                                $commands = $entity->getCommands();
                                $sender->sendMessage(TextFormat::colorize("&aCommand list for this HumanNPC:"));
                                foreach ($commands as $command) {
                                    $sender->sendMessage(TextFormat::colorize("&a+ &e" . $command));
                                }
                                break;

                            case 'name':
                            case 'rename':
                                if (!isset($args[3])) {
                                    $sender->sendMessage(TextFormat::colorize("&cUsage: /hnpc edit <npcId> rename <name>"));
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
                                    $sender->sendMessage(TextFormat::colorize("&cYou must hold an item in your hand."));
                                    break;
                                }

                                $entity->updateTool($sender, $sender->getInventory()->getItemInHand());
                                break;

                            default:
                                $sender->sendMessage(TextFormat::colorize("&cUsage: /hnpc edit <npcId> <addcmd|removecmd|listcmd|rename|settool|setskin>"));
                                break;
                        }
                        break;
                }
                return true;
        }

        return false;
    }

    public function onEntityDamage(EntityDamageEvent $event): void
    {
        if ($event instanceof EntityDamageByEntityEvent) {
            $damager = $event->getDamager();
            $entity = $event->getEntity();

            if ($damager instanceof Player && $entity instanceof HumanNPC) {
                $event->cancel();

                if (($commands = $entity->getCommands()) !== [] && !isset($this->npcIdGetter[$damager->getName()]) && !isset($this->npcRemover[$damager->getName()])) {
                    foreach ($commands as $command) {
                        $this->getServer()->dispatchCommand(new ConsoleCommandSender($this->getServer(), $this->getServer()->getLanguage()), str_replace('{player}', '"' . $damager->getName() . '"', $command));
                    }
                }

                if (isset($this->npcIdGetter[$damager->getName()])) {
                    $event->cancel();
                    $damager->sendMessage(TextFormat::colorize("&aThe ID of this HumanNPC is: &e" . $entity->getId()));
                    unset($this->npcIdGetter[$damager->getName()]);
                }

                if (isset($this->npcRemover[$damager->getName()])) {
                    $event->cancel();
                    $ev = new HumanRemoveEvent($entity, $damager);
                    $ev->call();
                    $entity->close();
                    $damager->sendMessage(TextFormat::colorize("&aHumanNPC removed successfully."));
                    unset($this->npcRemover[$damager->getName()]);
                }
            }
        }
    }

    public function onPlayerMove(PlayerMoveEvent $event): void
    {
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
