<?php

namespace RG;

use pocketmine\block\Block;

use pocketmine\command\{
	CommandSender,
	Command,
	ConsoleCommandSender
};

use pocketmine\event\{
	block\BlockBreakEvent,
	block\BlockPlaceEvent,
	block\SignChangeEvent,
	entity\EntityDamageEvent,
	entity\EntityDamageByEntityEvent,
	Listener,
	player\PlayerDeathEvent,
	player\PlayerInteractEvent,
	player\PlayerQuitEvent
};

use pocketmine\inventory\ChestInventory;
use pocketmine\item\item;

use pocketmine\level\{
	Level,
	Position
};

use pocketmine\math\Vector3;

use pocketmine\plugin\PluginBase;

use pocketmine\scheduler\Task;

use pocketmine\tile\{
	Tile,
	Sign
};

use pocketmine\utils\{
	Config,
	TextFormat
};

use pocketmine\Player;

class Main extends PluginBase implements Listener {

    public $Signprefix = TextFormat::GRAY . "[" . TextFormat::RED . "RageGames" . TextFormat::GRAY . "]";
    
    public $prefix = TextFormat::RED . "RG" . TextFormat::DARK_GRAY . " | " . TextFormat::GRAY;
    
    public $arenas = array();
    public $kit = array();
    public $signregister = false;
    public $signregisterWHO = false;
    public $temparena = "";

    public function onEnable() {
    	
         $this->getServer()->getLogger()->info("§7[§cRageGames§7] §bPlugin wurde §aaktiviert§b!");
        @mkdir($this->getDataFolder());
		
		if (!file_exists($this->getDataFolder() . "rg.yml")) {
            $kitcfg = new Config($this->getDataFolder() . "rg.yml", Config::YAML);
            $kitcfg->setNested("Kits", array("Kit1", "Kit2"));
            
            $kitcfg->setNested("Kit1.Helm", 0);
            $kitcfg->setNested("Kit1.Brust", 0);
            $kitcfg->setNested("Kit1.Hose", 0);
            $kitcfg->setNested("Kit1.Schuhe", 0);

            $kitcfg->setNested("Kit1.Items", array(
            array(Item::WOODEN_AXE, 0, 1), 
            array(Item::STONE_SWORD, 0, 1), 
            array(Item::BOW, 0, 1), 
            array(Item::ARROW, 0, 64)));
            
            $kitcfg->setNested("Kit2.Helm", 0);
            $kitcfg->setNested("Kit2.Brust", 0);
            $kitcfg->setNested("Kit2.Hose", 0);
            $kitcfg->setNested("Kit2.Schuhe", 0);

            $kitcfg->setNested("Kit2.Items", array(
            array(Item::WOODEN_AXE, 0, 1), 
            array(Item::STONE_SWORD, 0, 1), 
            array(Item::BOW, 0, 1), 
            array(Item::ARROW, 0, 64)));
            $kitcfg->save();
        }
		
        $this->getServer()->getPluginManager()->registerEvents($this, $this);
        $config = new Config($this->getDataFolder() . "config.yml", Config::YAML);

        if ($config->get("arenas") == null) {
            $config->set("arenas", array("Arena1"));
            $config->save();
        }
        $this->arenas = $config->get("arenas");
        foreach ($this->arenas as $arena) {
			if($arena != "Arena1"){
				$this->resetArena($arena);
				if (file_exists($this->getServer()->getDataPath() . "worlds/" . $arena)) {
					$this->getLogger()->Info("Arena -> " . $arena . " <- wurde geladen");
					$this->getServer()->loadLevel($arena);
				}
			}
        }

        $this->getScheduler()->scheduleRepeatingTask(new GameSender($this), 20);
        $this->getScheduler()->scheduleRepeatingTask(new RefreshSigns($this), 20);
        
    }

    public function resetArena($arena) {
        $config = new Config($this->getDataFolder() . "config.yml", Config::YAML);
        $level = $this->getServer()->getLevelByName($arena);
        if ($level instanceof Level) {
            $this->getServer()->unloadLevel($level);
            $this->getServer()->loadLevel($arena);
        }
        $config->set($arena . "LobbyTimer", 31);
        $config->set($arena . "EndTimer", 6);
        $config->set($arena . "Status", "Lobby");
        $config->save();
    }
    public function onBreak(BlockBreakEvent $event) {
        $player = $event->getPlayer();

        $config = new Config($this->getDataFolder() . "config.yml", Config::YAML);
        $welt = $player->getLevel()->getFolderName();

        if(in_array($welt, $this->arenas)){
            $event->setCancelled(TRUE);
            $player->sendMessage($this->prefix . "Du kannst hier keine Blöcke abbauen!");
        }
    }

    public function onPlace(BlockPlaceEvent $event) {
        $player = $event->getPlayer();

        $config = new Config($this->getDataFolder() . "config.yml", Config::YAML);
        $welt = $player->getLevel()->getFolderName();

        if (in_array($welt, $this->arenas)) {
			$event->setCancelled(TRUE);
			$player->sendMessage($this->prefix . "Du kannst hier keine Blöcke setzen!");
        }
    }
	public function onHit(EntityDamageEvent $event){
			
		$config = new Config($this->getDataFolder() . "config.yml", Config::YAML);
		if($event->getEntity() instanceof Player){
		$entity = $event->getEntity();
		
		if(in_array($entity->getLevel()->getFolderName(), $this->arenas)){
			if($config->get($event->getEntity()->getLevel()->getFolderName()."Status") == "Lobby"){
				$event->setCancelled();
			}
		}
		
		if($event instanceof EntityDamageByEntityEvent){
		
			if($event->getEntity() instanceof Player && $event->getDamager() instanceof Player){
			
				$victim = $event->getEntity();
				$status = "-";
				$damager = $event->getDamager();
			
				if(in_array($event->getEntity()->getLevel()->getFolderName(), $this->arenas)){
					if($config->get($victim->getLevel()->getFolderName()."Status") == "Lobby"){
						$event->setCancelled();
					}
				}
			}
		}
		}
	}
	
    public function onQuit(PlayerQuitEvent $event) {

        $config = new Config($this->getDataFolder() . "config.yml", Config::YAML);
        $player = $event->getPlayer();
        $name = $player->getName();
        $welt = $player->getLevel()->getFolderName();

        $status = "-";

        if (in_array($welt, $this->arenas)) {
            $status = $config->get($welt . "Status");
        }


        if (in_array($player->getLevel()->getFolderName(), $this->arenas)) {

            foreach ($player->getLevel()->getPlayers() as $p) {
                if ($status != "Lobby") {
                }
            }
        }
    }
    public function onDeath(PlayerDeathEvent $event) {

        $config = new Config($this->getDataFolder() . "config.yml", Config::YAML);
        $player = $event->getEntity();
        $player->removeAllEffects();
		$name = $player->getName();
        $welt = $player->getLevel()->getFolderName();
		
        $status = "-";

        if (in_array($welt, $this->arenas)) {
            $status = $config->get($welt . "Status");

            if (in_array($player->getLevel()->getFolderName(), $this->arenas)) {

                foreach ($player->getLevel()->getPlayers() as $p) {
                    if ($status != "Lobby") {
                        $aliveplayers = count($this->getServer()->getLevelByName($welt)->getPlayers());
                        $aliveplayers--;
                    }
                }
            }
        }
    }

    public function onInteract(PlayerInteractEvent $event) {

        $config = new Config($this->getDataFolder() . "config.yml", Config::YAML);
        $block = $event->getBlock();
        $blockID = $block->getID();
        $player = $event->getPlayer();
        $arena = $player->getLevel()->getFolderName();
        $tile = $player->getLevel()->getTile($block);

        if ($tile instanceof Sign) {

            if ($this->signregister === true && $this->signregisterWHO == $player->getName()) {
                $tile->setText($this->Signprefix, $this->temparena, TextFormat::GREEN . "Lade Runde...", "");
                $this->signregister = false;
            }

            $text = $tile->getText();
            if ($text[0] == $this->Signprefix) {
                if ($text[2] ==TextFormat::GRAY . "[" . TextFormat::GREEN . "Beitreten" . TextFormat::GRAY . "]") {
                    $spieleranzahl = count($this->getServer()->getLevelByName($text[1])->getPlayers());
                    if ($spieleranzahl < 2) {
                        $level = $this->getServer()->getLevelByName($text[1]);
                        $spawn = $level->getSafeSpawn();
                        $level->loadChunk($spawn->getX(), $spawn->getZ());
                        $player->teleport($spawn, 0, 0);
                        $player->getInventory()->clearAll();
                        $player->getArmorInventory()->clearAll();

                        $player->removeAllEffects();
                        $player->setFood(20);
                        $player->setHealth(20);
                        
                        $player->getInventory()->setItem(4, Item::get(399)->setCustomName(TextFormat::RESET . TextFormat::GOLD . "Zurück zur Lobby"));
                    } else {
                        $player->sendMessage($this->prefix . "Arena " . $text[1] . " ist voll!");
                    }
                } else {
                    $player->sendMessage($this->prefix . "Du bist der Runde beigetreten!");
                }
            }
        }
    }
	
	public function getRandomKit(){
		$kitcfg = new Config($this->getDataFolder() . "rg.yml", Config::YAML);
		$allkits = $kitcfg->get("Rg");
		$randomindex = mt_rand(0, count($allkits)-1);
		
		return $allkits[$randomindex];
	}
	
	public function giveKit(Player $player) {
        $name = $player->getName();

        if (!isset($this->kit[$name])) {
            $player->sendMessage($this->prefix . "Du hast kein Kit erhalten!");
        } else {
            $kitname = $this->kit[$name];


            $kitcfg = new Config($this->getDataFolder() . "rg.yml", Config::YAML);
            $inv = $player->getInventory();
            $ainv = $player->getArmorInventory();

            $inv->clearAll();
            $player->removeAllEffects();

            $helm = $kitcfg->getNested($kitname . ".Helm");
            $brust = $kitcfg->getNested($kitname . ".Brust");
            $hose = $kitcfg->getNested($kitname . ".Hose");
            $schuhe = $kitcfg->getNested($kitname . ".Schuhe");

            $items = $kitcfg->getNested($kitname . ".Items");

            $ainv->setHelmet(Item::get($helm, 0, 1));
            $ainv->setChestplate(Item::get($brust, 0, 1));
            $ainv->setLeggings(Item::get($hose, 0, 1));
            $ainv->setBoots(Item::get($schuhe, 0, 1));

            foreach ($items as $i) {
            			
				$player->getInventory()->addItem(Item::get($i[0], $i[1], $i[2]));
            }

            $player->sendMessage($this->prefix . "Du hast das Kit " . TextFormat::GOLD . $kitname . TextFormat::GRAY . " erhalten!");
        }
    }
	
    public function onCommand(CommandSender $sender, Command $cmd, string $label, array $args) : bool {
        $config = new Config($this->getDataFolder() . "config.yml", Config::YAML);
		$arena = $sender->getLevel()->getFolderName();
		
        if (in_array($arena, $this->arenas)) {
            $status = $config->get($arena . "Status");
        } else {
            $status = "NO-ARENA";
        }
        
        if ($cmd->getName() == "leave") {
            $sender->teleport($this->getServer()->getDefaultLevel()->getSafeSpawn());
            $sender->setFood(20);
            $sender->setHealth(20);
            $sender->getInventory()->clearAll();
            $sender->getArmorInventory()->clearAll();
            $sender->removeAllEffects();
        }
        
        if ($cmd->getName() == "rg") {
            if (!empty($args[0])) {
                if ($args[0] == "addarena" && $sender->isOP()) {
                    if (!empty($args[1])) {
                        if (file_exists($this->getServer()->getDataPath() . "worlds/" . $args[1])) {
                            $arena = $args[1];
                            $this->arenas[] = $arena;
                            $config->set("arenas", $this->arenas);
                            $config->save();
                            $this->resetArena($arena);
                            $sender->sendMessage($this->prefix . "Du hast eine neue Arena erstellt!");
                        }
                    }
                    } elseif (strtolower($args[0]) == "regsign" && $sender->isOP()) {
                    if (!empty($args[1])) {

                        $this->signregister = true;
                        $this->signregisterWHO = $sender->getName();
                        $this->temparena = $args[1];

                        $sender->sendMessage($this->prefix . "Tippe nun ein schild an um es zu registrieren");
                    }
                } elseif (strtolower($args[0]) == "help" && $sender->isOP()) {
                    $sender->sendMessage(TextFormat::GRAY . "» /rg setspawn <SpawnID>");
                    $sender->sendMessage(TextFormat::GRAY . "» /rg addarena <Weltname>");
                    $sender->sendMessage(TextFormat::GRAY . "» /rg regsign <Arena>");
                } elseif (strtolower($args[0]) == "setspawn" && $sender->isOP()) {
                    if (!empty($args[1])) {
                        $arena = $sender->getLevel()->getFolderName();
                        $x = $sender->getX();
                        $y = $sender->getY();
                        $z = $sender->getZ();
                        $coords = array($x, $y, $z);

                        $config->set($arena . "Spawn" . $args[1], $coords);
                        $config->save();
                        $sender->sendMessage($this->prefix . "Du hast Spawn " . $args[1] . " der Arena " . TextFormat::GOLD . $arena . TextFormat::GRAY . " gesetzt!");
                    }
                } else {
                    $sender->sendMessage(TextFormat::GRAY . "» /rg setspawn <SpawnID>");
                    $sender->sendMessage(TextFormat::GRAY . "» /rg addarena <Weltname>");
                    $sender->sendMessage(TextFormat::GRAY . "» /rg regsign <Arena>");
                }
            }
        }
        return false;
    }

}

class RefreshSigns extends Task {

 	public $prefix = "";
    	public $Signprefix ="";

    	public function __construct($plugin) {
        	$this->plugin = $plugin;
        	$this->prefix = $this->plugin->prefix;
        	$this->Signprefix = $this->plugin->Signprefix;
    	}

    	public function onRun($tick) {
        	$allplayers = $this->plugin->getServer()->getOnlinePlayers();
        	$level = $this->plugin->getServer()->getDefaultLevel();
        	$tiles = $level->getTiles();
        	foreach ($tiles as $t) {
            		if ($t instanceof Sign) {
                		$text = $t->getText();
                		if ($text[0] == $this->Signprefix) {
                    			$aop = count($this->plugin->getServer()->getLevelByName($text[1])->getPlayers());
                    			$ingame = TextFormat::GRAY . "[" . TextFormat::GREEN . "Beitreten" . TextFormat::GRAY . "]";
                    			$config = new Config($this->plugin->getDataFolder() . "config.yml", Config::YAML);
                    			if ($config->get($text[1] . "Status") != "Lobby") {
                        			$ingame = TextFormat::GRAY . "[" . TextFormat::RED . "InGame" . TextFormat::GRAY . "]";
                    			}
                    			if ($aop >= 2) {
                        			$ingame = TextFormat::GRAY . "[" . TextFormat::DARK_RED . "Voll" . TextFormat::GRAY . "]";
                    			}
                    			if ($config->get($text[1] . "Status") == "Ende") {
                        			$ingame = TextFormat::GRAY . "[" . TextFormat::RED . "Lade Runde..." . TextFormat::GRAY . "]";
                    			}
                    			$t->setText($this->Signprefix, $text[1], $ingame, TextFormat::BLUE . $aop . " / 16");
                		}
            		}
        	}
    	}
}

class GameSender extends Task {

	public $prefix = "";

    	public function __construct($plugin) {
        	$this->plugin = $plugin;
        	$this->prefix = $this->plugin->prefix;
        	$this->Signprefix = $this->plugin->Signprefix;
    	}

    	public function onRun($tick) {
        	$config = new Config($this->plugin->getDataFolder() . "config.yml", Config::YAML);
        	$arenas = $config->get("arenas");
        	if (count($arenas) != 0) {
            		foreach ($arenas as $arena) {
                		$status = $config->get($arena . "Status");
                		$lobbytimer = $config->get($arena . "LobbyTimer");
                		$endtimer = $config->get($arena . "EndTimer");
                		$levelArena = $this->plugin->getServer()->getLevelByName($arena);
                		if ($levelArena instanceof Level) {
                    			$players = $levelArena->getPlayers();
					if ($status == "Lobby") {
                        			if (count($players) < 2) {
                            				$config->set($arena . "LobbyTimer", 31);
                            				$config->set($arena . "EndTimer", 6);
                            				$config->set($arena . "Status", "Lobby");
                            				$config->save();

                            				foreach ($players as $p){

                            				}

                            				if ((Time() % 20) == 0) {
                                				foreach ($players as $p) {

                                				}
                            				}
                        			} else {

                            			$lobbytimer--;
                            			$config->set($arena . "LobbyTimer", $lobbytimer);
                            			$config->save();
                            			if ($lobbytimer >= 1 && $lobbytimer <= 30) {
                                			foreach ($players as $p) {
                                    			$p->addTitle($this->Signprefix, TextFormat::GREEN . $lobbytimer);
                                			}
                            			}
                            			if ($lobbytimer <= 0) {

                                			$countPlayers = 0;
								
							$rndkit = $this->plugin->getRandomKit();
								
                                			foreach ($players as $p) {
                                    			$countPlayers++;

                                    			$spawn = $config->get($arena . "Spawn" . $countPlayers);
                                    			$p->teleport(new Vector3($spawn[0], $spawn[1], $spawn[2]));
									
                                    			$p->setFood(20);
                                    			$p->setHealth(20);
                                    			$p->getInventory()->clearAll();
                                    			$p->getArmorInventory()->clearAll();
                                    			$p->removeAllEffects();
                                    					
								foreach($p->getLevel()->getPlayers() as $online){
									$p->showPlayer($online);
								}
								$this->plugin->kit[$p->getName()] = $rndkit;
								$this->plugin->giveKit($p);
                                			}

                                			$config->set($arena . "Status", "InGame");
                                			$config->save();
                            			}
                        		}
                    		}
                    		if ($status == "InGame") {
						
                        		if (count($players) <= 1) {
                            			foreach ($players as $p) {
						}

                            		$config->set($arena . "Status", "Ende");
                            		$config->save();
                        		}
						
						
                    		}
                    		if ($status == "Ende") {

                        		if ($endtimer >= 0) {
                            			$endtimer--;
                            			$config->set($arena . "EndTimer", $endtimer);
                            			$config->save();

                            			if ($endtimer == 15 ||
                                    		$endtimer == 10 ||
                                    		$endtimer == 5 ||
                                    		$endtimer == 4 ||
                                    		$endtimer == 3 ||
                                    		$endtimer == 2 ||
                                    		$endtimer == 1
                            			) {

                                			foreach ($players as $p) {
                                    			$p->addTitle($this->Signprefix, TextFormat::GREEN . $endtimer);
                                			}
                            			}

                            			if ($endtimer == 0) {

                                			$config = new Config($this->plugin->getDataFolder() . "config.yml", Config::YAML);

                                			foreach ($players as $p) {
                                    			$name = $p->getName();
								$p->teleport($this->plugin->getServer()->getDefaultLevel()->getSafeSpawn());
                                    			$p->setFood(20);
                                    			$p->setHealth(20);
                                    			$p->getInventory()->clearAll();
                                    			$p->getArmorInventory()->clearAll();
                                    			$p->removeAllEffects();
									
                                			}
                                			$config = new Config($this->plugin->getDataFolder() . "config.yml", Config::YAML);
                               	 		$config->set($arena . "LobbyTimer", 31);
                                			$config->set($arena . "EndTimer", 6);
                                			$config->set($arena . "Status", "Lobby");
                                			$config->save();
                            				}
                        			}
                    			}
                		}
            		}
        	}
	}
}
