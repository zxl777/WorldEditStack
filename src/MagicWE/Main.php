<?php

/*
 * MagicWE
 * WorldEdit for PocketMine
 *
 * https://github.com/thebigsmileXD/MagicWE
 *
 * Made by @thebigsmileXD / @XenialDan and @svilex!
 * Thanks so much to @svilex for creating most of the schematics import + export code! (https://github.com/svilex/Schematic_Loader)
 * You are so awesome, dude! Couldn't have got this done without you!
 *
 * Thanks for all suggestions from you all!
 *
 * https://github.com/thebigsmileXD
 * https://github.com/svilex

 源码和API查询
 https://github.com/pmmp/PocketMine-MP

 PHP语言文档查询
 http://php.net/manual/en/function.floor.php
 */
namespace MagicWE;

use pocketmine\Player;
use pocketmine\command\Command;
use pocketmine\utils\TextFormat;
use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\command\CommandSender;
use pocketmine\math\Vector3;
use pocketmine\level\Level;
use pocketmine\level\Position;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\item\Item;
use pocketmine\event\player\PlayerInteractEvent;

class Main extends PluginBase implements Listener{
	public $areas;
	private $pos1 = [], $pos2 = [], $copy = [], $copypos = [], $undo = [], $redo = [], $wand = [], $schematics = [];
	private static $MAX_BUILD_HEIGHT = 128;

	public function onLoad(){
		$this->getLogger()->info(TextFormat::GREEN . "MagicWE has been loaded!");
	}

	public function onEnable(){
		$this->saveResource("config.yml");
		@mkdir($this->getDataFolder() . "schematics");
		$this->getServer()->getPluginManager()->registerEvents($this, $this);
		$this->getLogger()->info(TextFormat::GREEN . "MagicWE enabled!");
	}

	public function onCommand(CommandSender $sender, Command $command, $label, array $args){
		if($sender instanceof Player){
			switch($command){
				case "/help":
				{
					$sender->sendMessage("首先要找power777888申请OP权限\n//set 方块ID   填充方块\n//wand   开/关法杖功能\n//replace 需要被替换的方块的ID 替换成哪个方块的ID   替换方块\n//pos1   使用指令设置第一个点\n//pos2   使用指令设置第二个点\n//undo   撤回上一次的操作\n//cyl 方块ID 半径   你的位置为中心点生成一个实心圆柱体\n//hcyl 方块ID 半径   你的位置为中心生成一个空心圆柱体\n//redo   //undo的反向指令\n//copy   把你选定的区域内的方块复制到“剪贴板”里（如果你是站在要复制的这个东西的左边输入//copy，那你要//paste的时候这个东西就在你的左边，其他的方向如此）\n//paste   把剪贴板里的建筑复制出来");
					break;
				}

				case "/pos1":
					{
						if(!$sender->hasPermission("we.command.pos1") && !$sender->hasPermission("we.command.admin")) return;
						$pos1x = $sender->getFloorX();
						$pos1y = $sender->getFloorY();
						$pos1z = $sender->getFloorZ();
						$this->pos1[$sender->getName()] = new Vector3($pos1x, $pos1y, $pos1z);
						if($pos1y > self::$MAX_BUILD_HEIGHT || $pos1y < 0) $sender->sendMessage(TextFormat::GOLD . "[MagicWE] Warning: You are above y:" . self::$MAX_BUILD_HEIGHT . " or below y:0");
						$sender->sendMessage(TextFormat::GREEN . "[MagicWE] 点1已设置在 x:" . $pos1x . " y:" . $pos1y . " z:" . $pos1z);
						return true;
						break;
					}

				case "/pos2":
					{
						if(!$sender->hasPermission("we.command.pos2") && !$sender->hasPermission("we.command.admin")) return;
						$pos2x = $sender->getFloorX();
						$pos2y = $sender->getFloorY();
						$pos2z = $sender->getFloorZ();
						$this->pos2[$sender->getName()] = new Vector3($pos2x, $pos2y, $pos2z);
						if($pos2y > self::$MAX_BUILD_HEIGHT || $pos2y < 0) $sender->sendMessage(TextFormat::GOLD . "[MagicWE] Warning: You are above y:" . self::$MAX_BUILD_HEIGHT . " or below y:0");
						$sender->sendMessage(TextFormat::GREEN . "[MagicWE] 点2已设置在 x:" . $pos2x . " y:" . $pos2y . " z:" . $pos2z);
						return true;
						break;
					}

				case "/set":
					{
						if(!$sender->hasPermission("we.command.set") && !$sender->hasPermission("we.command.admin")) return;
						if(isset($args[0])){
							if(isset($this->pos1[$sender->getName()], $this->pos2[$sender->getName()])){
								$this->fill($sender, $args[0]);
								$sender->getLevel()->doChunkGarbageCollection();
								return true;
							}
						}
						else{
							$sender->sendMessage(TextFormat::RED . "[MagicWE] 填充失败");
						}
						break;
					}

				case "/replace":
					{
						if(!$sender->hasPermission("we.command.replace") && !$sender->hasPermission("we.command.admin")) return;
						if(isset($args[0]) && isset($args[1])){
							if(isset($this->pos1[$sender->getName()], $this->pos2[$sender->getName()])){
								$this->replace($sender, $args[0], $args[1]);
								$sender->getLevel()->doChunkGarbageCollection();
								return true;
							}
						}
						else{
							$sender->sendMessage(TextFormat::RED . "[MagicWE] 替换失败");
						}
						break;
					}

				case "/copy":
					{
						if(!$sender->hasPermission("we.command.copy") && !$sender->hasPermission("we.command.admin")) return;
						if(isset($this->pos1[$sender->getName()], $this->pos2[$sender->getName()])){
							$this->copy($sender);
							return true;
						}
						break;
					}

				case "/paste":
					{
						if(!$sender->hasPermission("we.command.paste") && !$sender->hasPermission("we.command.admin")) return;
						if(isset($this->pos1[$sender->getName()], $this->pos2[$sender->getName()])){
							$this->paste($sender);
							$sender->getLevel()->doChunkGarbageCollection();
							return true;
						}
						break;
					}

					case "/stack":
						{
							if(!$sender->hasPermission("we.command.stack") && !$sender->hasPermission("we.command.admin")) return;

							$yaw = $sender->getYaw();
							$pitch = $sender->getPitch();
							$this->getLogger()->info($yaw);
							$this->getLogger()->info($pitch);

							if(isset($this->pos1[$sender->getName()], $this->pos2[$sender->getName()])){
								$count = 1;
								if(isset($args[0])){
									$count = $args[0];

									$this->stack($sender,$count);
									$sender->getLevel()->doChunkGarbageCollection();
								}
								else {
									$sender->sendMessage(TextFormat::RED . "[MagicWE] 延伸失败");
								}
								return true;
							}
							break;
						}


				case "/undo":
					{
						if(!$sender->hasPermission("we.command.undo") && !$sender->hasPermission("we.command.admin")) return;
						if(!empty($this->undo[$sender->getName()])){
							$this->undo($sender);
							$sender->getLevel()->doChunkGarbageCollection();
							return true;
						}
						else{
							$sender->sendMessage(TextFormat::RED . "[MagicWE] 撤回失败");
						}
						break;
					}

				case "/redo":
					{
						if(!$sender->hasPermission("we.command.redo") && !$sender->hasPermission("we.command.admin")) return;
						if(!empty($this->redo[$sender->getName()])){
							$this->redo($sender);
							$sender->getLevel()->doChunkGarbageCollection();
							return true;
						}
						else{
							$sender->sendMessage(TextFormat::RED . "[MagicWE] 撤销失败");
						}
						break;
					}

				case "/flip":
					{
						if(!$sender->hasPermission("we.command.flip") && !$sender->hasPermission("we.command.admin")) return;
						if(!empty($this->copy[$sender->getName()]) && isset($args[0])){
							if(!in_array($args[0], array("x", "y", "z"))) return false;
							$this->flip($sender, $args[0]);
							return true;
						}
						elseif(!isset($args[0])){
							$sender->sendMessage(TextFormat::RED . "[MagicWE] invalid argments");
						}
						else{
							$sender->sendMessage(TextFormat::RED . "[MagicWE] Nothing to flip, use //copy first");
						}
						break;
					}

				case "toggleeditwand":
				case "/wand":
					{
						if(!$sender->hasPermission("we.command.wand") && !$sender->hasPermission("we.command.admin")) return;
						if(empty($this->wand[$sender->getName()]) || $this->wand[$sender->getName()] === 0){
							$this->wand[$sender->getName()] = 1;
							$sender->sendMessage(TextFormat::GREEN . "[MagicWE] 小木斧定位功能开启");
						}
						else{
							$this->wand[$sender->getName()] = 0;
							$sender->sendMessage(TextFormat::RED . "[MagicWE] 小木斧定位功能关闭");
						}
						return true;
						break;
					}

				case "/schem":
					{
						if(!$sender->hasPermission("we.command.schem") && !$sender->hasPermission("we.command.admin")) return;
						if(empty($args) || empty($args[0]) || empty($args[1])){
							$sender->sendMessage(TextFormat::RED . "[MagicWE] Invalid option");
						}
						elseif($args[0] === "load"){
							$this->schematics[$args[1]] = $this->loadSchematic($sender, $args[1]);
							if($this->schematics[$args[1]] instanceof SchematicLoader){
								$sender->sendMessage(TextFormat::GREEN . "[MagicWE] Schematic $args[1] sucessfully loaded into cache. Use //schem paste to paste");
								return true;
							}
							else{
								$sender->sendMessage(TextFormat::RED . "[MagicWE] Incorrect schematic file or not loaded. Use //schem load <filename> to load a schematic");
							}
							return false;
						}
						elseif($args[0] === "paste"){
							if(isset($this->schematics[$args[1]]) && $this->schematics[$args[1]] instanceof SchematicLoader){
								$success = $this->pasteSchematic($sender, $sender->getLevel(), $sender->getPosition(), $this->schematics[$args[1]]);
								if($success){
									$sender->sendMessage(TextFormat::GREEN . "[MagicWE] Schematic $args[1] sucessfully pasted");
									$sender->getLevel()->doChunkGarbageCollection();
									return true;
								}
							}
							$sender->sendMessage(TextFormat::RED . "[MagicWE] Incorrect schematic file or not loaded. Use //schem load <filename> to load a schematic");
							return false;
						}
						elseif($args[0] === "save" || $args[0] === "export"){
							if(isset($this->pos1[$sender->getName()], $this->pos2[$sender->getName()])){
								$success = $this->exportSchematic($sender, $args[1]);
								if($success){
									$sender->sendMessage(TextFormat::GREEN . "[MagicWE] Selection sucessfully saved as $args[1].schematic");
									return true;
								}
							}
							$sender->sendMessage(TextFormat::RED . "[MagicWE] Can't save as $args[1]! Maybe a file wih that name already exists or you don't have write permission in this path!");
							return false;
						}
						break;
					}

				case "/cyl":
					{
						if(!$sender->hasPermission("we.command.cyl") && !$sender->hasPermission("we.command.admin")) return;
						if(isset($args[0], $args[1])){
							#$this->fill($sender, $args[0]);
							$this->W_cylinder($sender, $sender->getPosition(), $args[0], $args[1], $args[2]??1);
							$sender->getLevel()->doChunkGarbageCollection();
							return true;
						}
						else{
							$sender->sendMessage(TextFormat::RED . "[MagicWE] 生成失败");
						}
						break;
					}

				case "/hcyl":
					{
						if(!$sender->hasPermission("we.command.hcyl") && !$sender->hasPermission("we.command.admin")) return;
						if(isset($args[0], $args[1])){
							#$this->fill($sender, $args[0]);
							$this->W_holocylinder($sender, $sender->getPosition(), $args[0], $args[1], $args[2]??1);
							$sender->getLevel()->doChunkGarbageCollection();
							return true;
						}
						else{
							$sender->sendMessage(TextFormat::RED . "[MagicWE] 生成失败");
						}
						break;
					}
				default:
					{
						return false;
					}
			}
		}
		else{
			$sender->sendMessage(TextFormat::RED . "[MagicWE] This command must be used in-game");
		}
		return false;
	}

	public function wandPos1(BlockBreakEvent $event){
		$sender = $event->getPlayer();
		$block = $event->getBlock()->floor();
		// if($sender->hasPermission("we.command.wand") && !$sender->hasPermission("we.command.admin") && $sender->getInventory()->getItemInHand()->getId() === Item::WOODEN_AXE && $this->wand[$sender->getName()] === 1){
    if($sender->hasPermission("we.command.wand") && $sender->hasPermission("we.command.admin") && $sender->getInventory()->getItemInHand()->getId() === Item::WOODEN_AXE && $this->wand[$sender->getName()] === 1){
			if($block->y > self::$MAX_BUILD_HEIGHT || $block->y < 0) $sender->sendMessage(TextFormat::GOLD . "[MagicWE] Warning: You are above y:" . self::$MAX_BUILD_HEIGHT . " or below y:0");
			$this->pos1[$sender->getName()] = $block;
			$sender->sendMessage(TextFormat::GREEN . "[MagicWE] 点1已设置在 x:" . $block->x . " y:" . $block->y . " z:" . $block->z);
			$event->setCancelled();
		}
	}

	public function wandPos2(PlayerInteractEvent $event){
		if($event->getAction() !== PlayerInteractEvent::RIGHT_CLICK_BLOCK) return;
		$sender = $event->getPlayer();
		$block = $event->getBlock()->floor();
		if($sender->hasPermission("we.command.wand") && $sender->hasPermission("we.command.admin") && $sender->getInventory()->getItemInHand()->getId() === Item::WOODEN_AXE && $this->wand[$sender->getName()] === 1){
			if($block->y > self::$MAX_BUILD_HEIGHT || $block->y < 0) $sender->sendMessage(TextFormat::GOLD . "[MagicWE] Warning: You are above y:" . self::$MAX_BUILD_HEIGHT . " or below y:0");
			$this->pos2[$sender->getName()] = $block;
			$sender->sendMessage(TextFormat::GREEN . "[MagicWE] 点2已设置在 x:" . $block->x . " y:" . $block->y . " z:" . $block->z);
			$event->setCancelled();
		}
	}

	public function fill(Player $player, $blockarg){
		$changed = 0;
		$time = microtime(TRUE);
		if(empty($blockarg) && $blockarg !== "0") return false;
		$level = $player->getLevel();
		$blocks = explode(",", $blockarg);
		$pos1 = $this->pos1[$player->getName()];
		$pos2 = $this->pos2[$player->getName()];
		$pos = new Vector3(min($pos1->x, $pos2->x), min($pos1->y, $pos2->y), min($pos1->z, $pos2->z));
		if(!isset($this->undo[$player->getName()])) $this->undo[$player->getName()] = [];
		$undoindex = count(array_keys($this->undo[$player->getName()]));
		$this->undo[$player->getName()][$undoindex] = [];
		for($x = $pos->x; $x <= max($pos1->x, $pos2->x); $x++){
			for($y = $pos->y; $y <= max($pos1->y, $pos2->y); $y++){
				if($y > self::$MAX_BUILD_HEIGHT || $y < 0) continue;
				for($z = $pos->z; $z <= max($pos1->z, $pos2->z); $z++){
					if(!$level->isChunkLoaded($x >> 4, $z >> 4)) $level->loadChunk($x >> 4, $z >> 4, true);
					array_push($this->undo[$player->getName()][$undoindex], $level->getBlock($vec = new Vector3($x, $y, $z)));
					$blockstring = $blocks[array_rand($blocks, 1)];
					$block = Item::fromString($blockstring)->getBlock();
					if($block->getId() === 0 && !(strtolower(explode(":", $blockstring)[0]) == "air" || explode(":", $blockstring)[0] == "0")){
						$player->sendMessage(TextFormat::RED . '[MagicWE] 没有这个方块: "' . $blockstring . '", aborting');
						$player->sendMessage(TextFormat::RED . "[MagicWE] 填充失败.");
						return;
					}
					// $level->setBlockIdAt($x, $y, $z, $block->getId());
					// $level->setBlockDataAt($x, $y, $z, $block->getDamage());
					if($level->setBlock($vec, $block, false, false)) $changed++;
				}
			}
		}
		$player->sendMessage(TextFormat::GREEN . "[MagicWE] 填充成功, 用时 " . round((microtime(TRUE) - $time), 2) . "秒, " . $changed . " 方块已填充.");
	}

	public function replace(Player $player, $blockarg1, $blockarg2){
		$changed = 0;
		$time = microtime(TRUE);
		if((empty($blockarg1) && $blockarg1 !== "0") || (empty($blockarg2) && $blockarg2 !== "0")) return false;
		$level = $player->getLevel();
		$blocks1 = explode(",", $blockarg1);
		$blocks2 = explode(",", $blockarg2);
		$pos1 = $this->pos1[$player->getName()];
		$pos2 = $this->pos2[$player->getName()];
		$pos = new Vector3(min($pos1->x, $pos2->x), min($pos1->y, $pos2->y), min($pos1->z, $pos2->z));
		if(!isset($this->undo[$player->getName()])) $this->undo[$player->getName()] = [];
		$undoindex = count(array_keys($this->undo[$player->getName()]));
		$this->undo[$player->getName()][$undoindex] = [];
		for($x = $pos->x; $x <= max($pos1->x, $pos2->x); $x++){
			for($y = $pos->y; $y <= max($pos1->y, $pos2->y); $y++){
				if($y > self::$MAX_BUILD_HEIGHT || $y < 0) continue;
				for($z = $pos->z; $z <= max($pos1->z, $pos2->z); $z++){
					if(!$level->isChunkLoaded($x >> 4, $z >> 4)) $level->loadChunk($x >> 4, $z >> 4, true);
					array_push($this->undo[$player->getName()][$undoindex], $level->getBlock($vec = new Vector3($x, $y, $z)));
					foreach($blocks1 as $blockstring1){
						$blocka = Item::fromString($blockstring1)->getBlock();
						if($blocka->getId() === 0 && !(strtolower(explode(":", $blockstring1)[0]) == "air" || explode(":", $blockstring1)[0] == "0")){
							$player->sendMessage(TextFormat::RED . '[MagicWE] 没有这个方块: "' . $blockstring1 . '", aborting');
							$player->sendMessage(TextFormat::RED . "[MagicWE] 替换失败.");
							return;
						}
						$block1 = $blocka->getId();
						$meta1 = (explode(":", $blockstring1)[1]??false);
						if($level->getBlockIdAt($x, $y, $z) == $block1 && ($meta1 === false || $level->getBlockDataAt($x, $y, $z) == $meta1)){
							$blockstring2 = $blocks2[array_rand($blocks2, 1)];
							$blockb = Item::fromString($blockstring2)->getBlock();
							if($blockb->getId() === 0 && !(strtolower(explode(":", $blockstring2)[0]) == "air" || explode(":", $blockstring2)[0] == "0")){
								$player->sendMessage(TextFormat::RED . '[MagicWE] 没有这个方块: "' . $blockstring2 . '", aborting');
								$player->sendMessage(TextFormat::RED . "[MagicWE] 替换失败.");
								return;
							}
							// $block2 = $blockb->getId();
							if($level->setBlock($vec, $blockb, false, false)) $changed++;
							// $level->setBlockIdAt($x, $y, $z, $block2);
							// $meta2 = (explode(":", $blockstring2)[1]??0);
							// $level->setBlockDataAt($x, $y, $z, $meta2);
						}
					}
				}
			}
		}
		$player->sendMessage(TextFormat::GREEN . "[MagicWE] 替换成功, 用时 " . round((microtime(TRUE) - $time), 2) . "秒, " . $changed . " 方块已替换.");
	}

	public function copy(Player $player){
		$level = $player->getLevel();
		$pos1 = $this->pos1[$player->getName()];
		$pos2 = $this->pos2[$player->getName()];

		$pos = new Vector3(min($pos1->x, $pos2->x), min($pos1->y, $pos2->y), min($pos1->z, $pos2->z));
		$this->copy[$player->getName()] = [];

		//pos 是建筑的原点 － 玩家坐标（Floor是取整数）＝ copypos ，也就是建筑相对于玩家的坐标。以玩家为原点。POS是以建筑本身的左下角为原点。
		$this->copypos[$player->getName()] = $pos->subtract($player->getPosition()->floor());

		for($x = 0; $x <= abs($pos1->x - $pos2->x); $x++){
			for($y = 0; $y <= abs($pos1->y - $pos2->y); $y++){
				if($y > self::$MAX_BUILD_HEIGHT || $y < 0) continue;
				for($z = 0; $z <= abs($pos1->z - $pos2->z); $z++){
					$this->copy[$player->getName()][$x][$y][$z] = $level->getBlock($pos->add($x, $y, $z));
				}
			}
		}
		$player->sendMessage(TextFormat::GREEN . "[MagicWE] 复制成功, 输入//paste即可粘贴, 输入//stack 次数  即可延伸建筑往你面相的方向。");
	}

	public function paste(Player $player){
		$time = microtime(TRUE);
		$level = $player->getLevel();
		$pos = $player->getPosition()->add($this->copypos[$player->getName()]);
		//pos 是建筑安放的原点 。

		if(!isset($this->undo[$player->getName()])) $this->undo[$player->getName()] = [];
		$undoindex = count(array_keys($this->undo[$player->getName()]));
		$this->undo[$player->getName()][$undoindex] = [];

		for($x = 0; $x < count(array_keys($this->copy[$player->getName()])); $x++){
			for($y = 0; $y < count(array_keys($this->copy[$player->getName()][$x])); $y++){
				if($y > self::$MAX_BUILD_HEIGHT || $y < 0) continue;
				for($z = 0; $z < count(array_keys($this->copy[$player->getName()][$x][$y])); $z++){
					if(!$level->isChunkLoaded($x >> 4, $z >> 4)) $level->loadChunk($x >> 4, $z >> 4, true);
					array_push($this->undo[$player->getName()][$undoindex], $level->getBlock(new Vector3($x, $y, $z)));
					$level->setBlockIdAt($pos->x + $x, $pos->y + $y, $pos->z + $z, $this->copy[$player->getName()][$x][$y][$z]->getId());
					$level->setBlockDataAt($pos->x + $x, $pos->y + $y, $pos->z + $z, $this->copy[$player->getName()][$x][$y][$z]->getDamage());
				}
			}
		}
		$player->sendMessage(TextFormat::GREEN . "[MagicWE] 粘贴成功, 用时 " . round((microtime(TRUE) - $time), 2) . "秒");
	}


	public function stack(Player $player, $count){
		$time = microtime(TRUE);
		$level = $player->getLevel();

		// $pos = $player->getPosition()->add($this->copypos[$player->getName()]);
		//pos 是建筑安放的原点 。
		$pos1 = $this->pos1[$player->getName()];
		$pos2 = $this->pos2[$player->getName()];

		$width 	= abs($pos1->x - $pos2->x);
		$length = abs($pos1->z - $pos2->z);
		$height = abs($pos1->y - $pos2->y);

		$pos = new Vector3(min($pos1->x, $pos2->x), min($pos1->y, $pos2->y), min($pos1->z, $pos2->z));


		$yaw = $player->getYaw();
		$pitch = $player->getPitch();
		$this->getLogger()->info($yaw);
		$this->getLogger()->info($pitch);

		if ($pitch>(-90-15)&&$pitch<(-90+15)) //+y
		{
			for ($i=0 ;$i<$count ; $i++)
			{
					$pos = $pos->add(0,$height+1,0);
					$this->PlaceOneCopyBuild($player, $pos);
			}
		}
		else if ($pitch>(90-15)&&$pitch<(90+15)) //+y
		{
			for ($i=0 ;$i<$count ; $i++)
			{
					$pos = $pos->add(0,-($height+1),0);
					$this->PlaceOneCopyBuild($player, $pos);
			}
		}

		else if ($yaw>(270-45)&&$yaw<(270+45)) //+x
		{
			for ($i=0 ;$i<$count ; $i++)
			{
					$pos = $pos->add($width+1,0,0);
					$this->PlaceOneCopyBuild($player, $pos);
			}
		}
		else if ($yaw>(90-45)&&$yaw<(90+45)) //-x
		{
			for ($i=0 ;$i<$count ; $i++)
			{
					$pos = $pos->add(-($width+1),0,0);
					$this->PlaceOneCopyBuild($player, $pos);
			}
		}
		else if ($yaw>(360-45)&&$yaw<=360||($yaw>=0&&$yaw<45)) //+z
		{
			for ($i=0 ;$i<$count ; $i++)
			{
					$pos = $pos->add(0,0,$length+1);
					$this->PlaceOneCopyBuild($player, $pos);
			}
		}
		else if ($yaw>(180-45)&&$yaw<(180+45)) //-z
		{
			for ($i=0 ;$i<$count ; $i++)
			{
					$pos = $pos->add(0,0,-($length+1));
					$this->PlaceOneCopyBuild($player, $pos);
			}
		}





		$player->sendMessage(TextFormat::GREEN . "[MagicWE] 延伸成功, 用时 " . round((microtime(TRUE) - $time), 2) . "秒");
	}

	public function PlaceOneCopyBuild(Player $player, Vector3 $pos) {

		$level = $player->getLevel();

		if(!isset($this->undo[$player->getName()])) $this->undo[$player->getName()] = [];
		$undoindex = count(array_keys($this->undo[$player->getName()]));
		$this->undo[$player->getName()][$undoindex] = [];


		for($x = 0; $x < count(array_keys($this->copy[$player->getName()])); $x++){
			for($y = 0; $y < count(array_keys($this->copy[$player->getName()][$x])); $y++){
				if($y > self::$MAX_BUILD_HEIGHT || $y < 0) continue;
				for($z = 0; $z < count(array_keys($this->copy[$player->getName()][$x][$y])); $z++){
					if(!$level->isChunkLoaded($x >> 4, $z >> 4)) $level->loadChunk($x >> 4, $z >> 4, true);
					array_push($this->undo[$player->getName()][$undoindex], $level->getBlock(new Vector3($x, $y, $z)));
					$level->setBlockIdAt($pos->x + $x, $pos->y + $y, $pos->z + $z, $this->copy[$player->getName()][$x][$y][$z]->getId());
					$level->setBlockDataAt($pos->x + $x, $pos->y + $y, $pos->z + $z, $this->copy[$player->getName()][$x][$y][$z]->getDamage());
				}
			}
		}
	}


	public function undo(Player $player){
		$time = microtime(TRUE);
		$level = $player->getLevel();
		if(!isset($this->undo[$player->getName()])) return;
		$undo = array_pop($this->undo[$player->getName()]);
		foreach($undo as $block){
			$level->setBlockIdAt($block->x, $block->y, $block->z, $block->getId());
			$level->setBlockDataAt($block->x, $block->y, $block->z, $block->getDamage());
		}
		$this->redo[$player->getName()][] = $undo;
		$player->sendMessage(TextFormat::GREEN . "[MagicWE] 撤回成功, 用时 " . round((microtime(TRUE) - $time), 2) . "秒");
	}

	public function redo(Player $player){
		$time = microtime(TRUE);
		$level = $player->getLevel();
		if(!isset($this->redo[$player->getName()])) return;
		$redo = array_pop($this->redo[$player->getName()]);
		foreach($redo as $block){
			$level->setBlockIdAt($block->x, $block->y, $block->z, $block->getId());
			$level->setBlockDataAt($block->x, $block->y, $block->z, $block->getDamage());
		}
		$this->undo[$player->getName()] = $redo;
		$player->sendMessage(TextFormat::GREEN . "[MagicWE] 撤销成功, 用时 " . round((microtime(TRUE) - $time), 2) . "秒");
	}

	public function flip(Player $player, $xyz){
		if($xyz === "x"){
			$this->copy[$player->getName()] = array_reverse($this->copy[$player->getName()]);
		}
		elseif($xyz === "y"){
			foreach(array_keys($this->copy[$player->getName()]) as $block){
				$this->copy[$player->getName()][$block] = array_reverse($this->copy[$player->getName()][$block]);
			}
		}
		elseif($xyz === "z"){
			foreach(array_keys($this->copy[$player->getName()]) as $block){
				foreach(array_keys($this->copy[$player->getName()][$block]) as $y){
					$this->copy[$player->getName()][$block][$y] = array_reverse($this->copy[$player->getName()][$block][$y]);
				}
			}
		}
		else
			return false;
		$player->sendMessage(TextFormat::GREEN . "[MagicWE] Clipboard flipped on $xyz-Axis");
	}

	// structures
	public function W_sphere(Player $player, Position $pos, $block, $radiusX, $radiusY, $radiusZ, $filled = true, &$output = null){
		$changed = 0;
		$time = microtime(TRUE);
		$block = Item::fromString($blockstring)->getBlock();
		if($block->getId() === 0 && !(strtolower(explode(":", $blockstring)[0]) == "air" || explode(":", $blockstring)[0] == "0")){
			$player->sendMessage(TextFormat::RED . '[MagicWE] No such block/item found: "' . $blockstring . '", aborting');
			$player->sendMessage(TextFormat::RED . "[MagicWE] Creating cylinder failed.");
			return;
		}
		$level = $pos->getLevel();

		$radiusX += 0.5;
		$radiusY += 0.5;
		$radiusZ += 0.5;

		$invRadiusX = 1 / $radiusX;
		$invRadiusY = 1 / $radiusY;
		$invRadiusZ = 1 / $radiusZ;

		$ceilRadiusX = (int) ceil($radiusX);
		$ceilRadiusY = (int) ceil($radiusY);
		$ceilRadiusZ = (int) ceil($radiusZ);

		// $bcnt = count ( $blocks ) - 1;
		$bcnt = 1; // only use selected block

		$nextXn = 0;
		$breakX = false;
		for($x = 0; $x <= $ceilRadiusX and $breakX === false; ++$x){
			$xn = $nextXn;
			$nextXn = ($x + 1) * $invRadiusX;
			$nextYn = 0;
			$breakY = false;
			for($y = 0; $y <= $ceilRadiusY and $breakY === false; ++$y){
				$yn = $nextYn;
				$nextYn = ($y + 1) * $invRadiusY;
				$nextZn = 0;
				$breakZ = false;
				for($z = 0; $z <= $ceilRadiusZ; ++$z){
					$zn = $nextZn;
					$nextZn = ($z + 1) * $invRadiusZ;
					$distanceSq = WorldEditBuilder::lengthSq($xn, $yn, $zn);
					if($distanceSq > 1){
						if($z === 0){
							if($y === 0){
								$breakX = true;
								$breakY = true;
								break;
							}
							$breakY = true;
							break;
						}
						break;
					}

					if($filled === false){
						if(WorldEditBuilder::lengthSq($nextXn, $yn, $zn) <= 1 and WorldEditBuilder::lengthSq($xn, $nextYn, $zn) <= 1 and WorldEditBuilder::lengthSq($xn, $yn, $nextZn) <= 1){
							continue;
						}
					}
					$blocktype = $block->getId();
					$this->upsetBlock2($level, $pos->add($x, $y, $z), $block);
					$count++;
					$this->upsetBlock2($level, $pos->add(-$x, $y, $z), $block);
					$count++;
					$this->upsetBlock2($level, $pos->add($x, -$y, $z), $block);
					$count++;
					$this->upsetBlock2($level, $pos->add($x, $y, -$z), $block);
					$count++;

					$this->upsetBlock2($level, $pos->add(-$x, -$y, $z), $block);
					$count++;
					$this->upsetBlock2($level, $pos->add($x, -$y, -$z), $block);
					$count++;
					$this->upsetBlock2($level, $pos->add(-$x, $y, -$z), $block);
					$count++;
					$this->upsetBlock2($level, $pos->add(-$x, -$y, -$z), $block);
					$count++;
				}
			}
		}
		$player->sendMessage(TextFormat::GREEN . "[MagicWE] Creating sphere succeed, took " . round((microtime(TRUE) - $time), 2) . "s, " . $changed . " Blocks changed.");
	}

	public function W_cylinder(Player $player, Position $pos, $blockstring, $radius, $height){
		$changed = 0;
		$time = microtime(TRUE);
		$block = Item::fromString($blockstring)->getBlock();
		if($block->getId() === 0 && !(strtolower(explode(":", $blockstring)[0]) == "air" || explode(":", $blockstring)[0] == "0")){
			$player->sendMessage(TextFormat::RED . '[MagicWE] No such block/item found: "' . $blockstring . '", aborting');
			$player->sendMessage(TextFormat::RED . "[MagicWE] Creating cylinder failed.");
			return;
		}
		for($a = -$radius; $a <= $radius; $a++){
			for($b = 0; $b < $height; $b++){
				for($c = -$radius; $c <= $radius; $c++){
					if($a * $a + $c * $c <= $radius * $radius){
						if($pos->getLevel()->setBlock(new Position($pos->x + $a, $pos->y + $b, $pos->z + $c, $pos->getLevel()), $block, false, false)) $changed++;
						$changed++;
					}
				}
			}
		}
		$player->sendMessage(TextFormat::GREEN . "[MagicWE] Creating cylinder succeed, took " . round((microtime(TRUE) - $time), 2) . "s, " . $changed . " Blocks changed.");
	}

	public function W_holocylinder(Player $player, Position $pos, $blockstring, $radius, $height){
		$changed = 0;
		$time = microtime(TRUE);
		$block = Item::fromString($blockstring)->getBlock();
		if($block->getId() === 0 && !(strtolower(explode(":", $blockstring)[0]) == "air" || explode(":", $blockstring)[0] == "0")){
			$player->sendMessage(TextFormat::RED . '[MagicWE] No such block/item found: "' . $blockstring . '", aborting');
			$player->sendMessage(TextFormat::RED . "[MagicWE] Creating cylinder failed.");
			return;
		}
		$changed = 0;
		for($a = -$radius; $a <= $radius; $a++){
			for($b = 0; $b < $height; $b++){
				for($c = -$radius; $c <= $radius; $c++){
					if($a * $a + $c * $c >= ($radius - 1) * ($radius - 1)){
						if($pos->getLevel()->setBlock(new Position($pos->x + $a, $pos->y + $b, $pos->z + $c, $pos->getLevel()), $block, false, false)) $changed++;
					}
				}
			}
		}
		$player->sendMessage(TextFormat::GREEN . "[MagicWE] Creating hollow cylinder succeed, took " . round((microtime(TRUE) - $time), 2) . "s, " . $changed . " Blocks changed.");
	}

	// schematic
	// TODO
	public function pasteSchematic(Player $player, Level $level, Position $loc, SchematicLoader $schematic){
		$blocks = $schematic->getBlocksArray();
		if(!isset($this->undo[$player->getName()])) $this->undo[$player->getName()] = [];
		$undoindex = count(array_keys($this->undo[$player->getName()]));
		$this->undo[$player->getName()][$undoindex] = [];
		foreach($blocks as $block){
			if($block[1] > self::$MAX_BUILD_HEIGHT) continue;
			if(!$level->isChunkLoaded($block[0] >> 4, $block[2] >> 4)) $level->loadChunk($block[0] >> 4, $block[2] >> 4, true);
			$blockloc = $loc->add($block[0], $block[1], $block[2]);
			array_push($this->undo[$player->getName()][$undoindex], $level->getBlock($blockloc));
			$level->setBlockIdAt($blockloc->getX(), $blockloc->getY(), $blockloc->getZ(), $block[3]);
			$level->setBlockDataAt($blockloc->getX(), $blockloc->getY(), $blockloc->getZ(), $block[4]);
		}
		return true;
	}

	public function loadSchematic(Player $player, $file){
		$path = $this->getDataFolder() . "schematics/" . $file . ".schematic";
		return new SchematicLoader($this, $path);
	}

	public function exportSchematic(Player $sender, $filename){
		$blocks = '';
		$data = '';
		$pos1 = $this->pos1[$sender->getName()];
		$pos2 = $this->pos2[$sender->getName()];
		$origin = new Vector3(min($pos1->x, $pos2->x), min($pos1->y, $pos2->y), min($pos1->z, $pos2->z));
		$w = abs($pos1->x - $pos2->x) + 1;
		$h = abs($pos1->y - $pos2->y) + 1;
		$l = abs($pos1->z - $pos2->z) + 1;
		$blocks = '';
		$data = '';
		for($y = 0; $y < $h; $y++){
			for($z = 0; $z < $l; $z++){
				for($x = 0; $x < $w; $x++){
					$block = $sender->getLevel()->getBlock($origin->add($x, $y, $z));
					$id = $block->getId();
					$damage = $block->getDamage();
					switch($id){
						case 158:
							$id = 126;
							break;
						case 157:
							$id = 125;
							break;
						case 126:
							$id = 157;
							break;
						case 85:
							switch($damage){
								case 1:
									$id = 188;
									$damage = 0;
									break;
								case 2:
									$id = 189;
									$damage = 0;
									break;
								case 3:
									$id = 190;
									$damage = 0;
									break;
								case 4:
									$id = 191;
									$damage = 0;
									break;
								case 5:
									$id = 192;
									$damage = 0;
									break;
								default:
									$damage = 0;
									break;
							}
							break;
						default:
							break;
					}
					$blocks .= chr($id);
					$data .= chr($damage);
				}
			}
		}
		$schematic = new SchematicExporter($blocks, $data, $w, $l, $h);
		return $schematic->saveSchematic(str_replace("//", "/", str_replace("\/", "/", $this->getDataFolder() . "/schematics/" . $filename . ".schematic")));
	}
}
