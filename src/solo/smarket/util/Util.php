<?php

namespace solo\smarket\util;

use pocketmine\item\ItemFactory;
use pocketmine\item\ItemIds;
use pocketmine\item\LegacyStringToItemParser;
use pocketmine\nbt\LittleEndianNbtSerializer;
use pocketmine\nbt\TreeRoot;
use pocketmine\player\Player;
use pocketmine\Server;
use pocketmine\item\Item;
use pocketmine\math\Vector3;
use pocketmine\world\Position;

class Util{

	public static $sitemdb = null;

	public static function init(){
		self::$sitemdb = Server::getInstance()->getPluginManager()->getPlugin("SItemDB");
	}

	public static function itemName(Item $item){
		if(self::$sitemdb !== null){
			$info = self::$sitemdb->getItemInfoByItem($item);
			if($info !== null){
				return $info->getName();
			}
		}
		if($item->hasCustomName()){
			return $item->getCustomName();
		}
		if(self::$sitemdb !== null){
			$info = self::$sitemdb->getItemInfoByItem(ItemFactory::getInstance()->get($item->getId(), $item->getMeta()));
			if($info !== null){
				return $info->getName();
			}
		}
		return $item->getName() ?? "Unknown";
	}

	public static function itemFullName(Item $item){
		return self::itemName($item) . " " . $item->getCount() . "개";
	}

	public static function itemHollCount(Player $player, Item $item){
		$count = 0;
		foreach($player->getInventory()->all($item) as $index => $content){
			if($index >= $player->getInventory()->getSize()){
				continue;
			}
			$count += $content->getCount();
		}
		return $count;
	}

	public static function parseItem(string $input){
		$item = null;
		// Parse : from SItemDB
		if(self::$sitemdb !== null){
			$item = self::$sitemdb->getItem($input);
		}

		// Parse : id or id:data
		if($item === null){
			$item = LegacyStringToItemParser::getInstance()->parse($input); // PocketMine parse
			if($item->getId() == ItemIds::AIR){
				return null;
			}
			// --- Default Parser ---
			//$token = explode(":", $input);
			//if(count($token) < 3 && is_numeric($token[0] ?? "Nan") && is_numeric($token[1] ?? 0)){
			//  $item = Item::get(intval($token[0]), intval($token[1] ?? 0));
			//}
		}
		return $item;
	}

	public static function itemHash(Item $item){
		$hash = $item->getId() . ":" . $item->getMeta();
		if($item->hasNamedTag()){
			$hash .= ":" . (new LittleEndianNbtSerializer())->write(new TreeRoot($item->getNamedTag()));
		}
		return $hash;
	}

	public static function vector3Hash(Vector3 $pos){
		return $pos->x . ":" . $pos->y . ":" . $pos->z;
	}

	public static function positionHash(Position $pos){
		return $pos->x . ":" . $pos->y . ":" . $pos->z . ":" . $pos->getWorld()->getFolderName();
	}

	public static function floor(Vector3 $pos){
		return $pos->floor();
	}

	public static function jsonDecode(string $string){
		$result = json_decode($string, true);
		$errorCode = json_last_error();
		if($errorCode !== JSON_ERROR_NONE){
			switch($errorCode){
				case JSON_ERROR_DEPTH:
					throw new \RuntimeException("The maximum stack depth has been exceeded");
				case JSON_ERROR_STATE_MISMATCH:
					throw new \RuntimeException("Invalid or malformed JSON");
				case JSON_ERROR_CTRL_CHAR:
					throw new \RuntimeException("Control character error, possibly incorrectly encoded");
				case JSON_ERROR_SYNTAX:
					throw new \RuntimeException("Syntax error, malformed JSON");
				case JSON_ERROR_RECURSION:
					throw new \RuntimeException("One or more NAN or INF values in the value to be encoded");
				case JSON_ERROR_UNSUPPORTED_TYPE:
					throw new \RuntimeException("A value of a type that cannot be encoded was given");
				default:
					throw new \RuntimeException("Unknown JSON error occured");
			}
		}
		return $result;
	}
}
