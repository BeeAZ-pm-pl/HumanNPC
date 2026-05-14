<?php

declare(strict_types=1);

namespace BeeAZ\HumanNPC\task;

use pocketmine\scheduler\AsyncTask;
use pocketmine\scheduler\ClosureTask;
use pocketmine\Server;
use pocketmine\entity\Skin;
use BeeAZ\HumanNPC\HumanNPC;
use pocketmine\utils\TextFormat;
use Ramsey\Uuid\Uuid;

class FetchSkinTask extends AsyncTask {

    private string $url;
    private int $entityId;
    private string $playerName;

    public function __construct(string $url, int $entityId, string $playerName) {
        $this->url = $url;
        $this->entityId = $entityId;
        $this->playerName = $playerName;
    }

    public function onRun(): void {
        $ch = curl_init($this->url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Accept: image/png, image/jpeg',
            'Cache-Control: no-cache'
        ]);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)');
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        
        $data = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($data === false || $httpCode !== 200) {
            $this->setResult(["success" => false]);
            return;
        }

        $src = @imagecreatefromstring($data);
        if ($src === false) {
            $this->setResult(["success" => false]);
            return;
        }

        $width = imagesx($src);
        $height = imagesy($src);

        if (!in_array($width, [64, 128]) || !in_array($height, [32, 64, 128])) {
            imagedestroy($src);
            $this->setResult(["success" => false]);
            return;
        }

        $is128 = ($width === 128 || $height === 128);
        $targetW = $is128 ? 128 : 64;
        $targetH = $is128 ? 128 : 64;

        $dst = imagecreatetruecolor($targetW, $targetH);
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        $transparent = imagecolorallocatealpha($dst, 0, 0, 0, 127);
        imagefill($dst, 0, 0, $transparent);
        
        imagecopy($dst, $src, 0, 0, 0, 0, $width, $height);
        imagedestroy($src);

        $bytes = "";
        for ($y = 0; $y < $targetH; $y++) {
            for ($x = 0; $x < $targetW; $x++) {
                $color = imagecolorat($dst, $x, $y);
                $a = ((~((int)($color >> 24))) << 1) & 0xff;
                $r = ($color >> 16) & 0xff;
                $g = ($color >> 8) & 0xff;
                $b = $color & 0xff;
                $bytes .= chr($r) . chr($g) . chr($b) . chr($a);
            }
        }
        imagedestroy($dst);
        $this->setResult(["success" => true, "data" => $bytes]);
    }

    public function onCompletion(): void {
        $server = Server::getInstance();
        $player = $server->getPlayerExact($this->playerName);
        $entity = $server->getWorldManager()->findEntity($this->entityId);
        $result = $this->getResult();

        if ($player !== null) {
            if ($result === null || $result["success"] === false) {
                $player->sendMessage(TextFormat::colorize("&c[Error] Failed to fetch or process the skin from URL."));
                return;
            }

            if ($entity instanceof HumanNPC && !$entity->isClosed()) {
                try {
                    $newSkinId = Uuid::uuid4()->toString();
                    
                    $newSkin = new Skin(
                        $newSkinId,
                        $result["data"],
                        "",
                        "geometry.humanoid.custom",
                        ""
                    );
                    
                    $entity->setSkin($newSkin);
                    $entity->sendSkin($entity->getViewers());
                    
                    $entity->despawnFromAll();
                    
                    $validPlugin = null;
                    foreach($server->getPluginManager()->getPlugins() as $p){
                        if($p->isEnabled()){
                            $validPlugin = $p;
                            break;
                        }
                    }
                    
                    if($validPlugin !== null){
                        $validPlugin->getScheduler()->scheduleDelayedTask(new ClosureTask(function() use ($entity): void {
                            if(!$entity->isClosed()){
                                $entity->spawnToAll();
                            }
                        }), 5);
                    } else {
                        $entity->spawnToAll();
                    }
                    
                    $player->sendMessage(TextFormat::colorize("&a[Success] Skin updated and reloaded successfully!"));
                } catch (\Exception $e) {
                    $player->sendMessage(TextFormat::colorize("&c[Error] Failed to apply skin: &f" . $e->getMessage()));
                }
            } else {
                $player->sendMessage(TextFormat::colorize("&c[Error] NPC not found or has been removed."));
            }
        }
    }
}