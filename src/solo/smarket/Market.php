<?php

namespace solo\smarket;

use onebone\economyapi\EconomyAPI;
use pocketmine\block\ItemFrame;
use pocketmine\item\Item;
use pocketmine\item\ItemFactory;
use pocketmine\nbt\LittleEndianNbtSerializer;
use pocketmine\nbt\TreeRoot;
use pocketmine\player\Player;
use solo\smarket\util\BuyException;
use solo\smarket\util\MarketDeserializeException;
use solo\smarket\util\SellException;
use solo\smarket\util\Util;

use function base64_decode;

class Market{

	private $id;

	private $item;

	private $buyPrice;
	private $sellPrice;

	public function __construct(int $id, Item $item, $buyPrice = -1, $sellPrice = -1){
		$this->id = $id;
		$this->item = ItemFactory::getInstance()->get($item->getId(), $item->getMeta(), 1, $item->getNamedTag());

		$this->setBuyPrice($buyPrice);
		$this->setSellPrice($sellPrice);
	}

	public function getId(){
		return $this->id;
	}

	public function getItem(){
		return clone $this->item;
	}

	public function getName(){
		return Util::itemName($this->item);
	}

	public function getBuyPrice(){
		return $this->buyPrice;
	}

	public function setBuyPrice($price){
		$this->buyPrice = ($price < 0) ? -1 : $price;
		return $this;
	}

	public function getSellPrice(){
		return $this->sellPrice;
	}

	public function setSellPrice($price){
		$this->sellPrice = ($price < 0) ? -1 : $price;
		return $this;
	}

	public function buy(Player $player, int $count){
		if($count < 1){
			throw new BuyException("갯수는 양수로 입력해주세요.");
		}
		$price = $this->buyPrice * $count;
		if($price < 0){
			throw new BuyException("구매 불가능한 아이템입니다.");
		}
		$item = clone $this->item;
		$item->setCount($count);
		if(!$player->getInventory()->canAddItem($item)){
			throw new BuyException("인벤토리에 공간이 부족합니다.");
		}
		if(EconomyAPI::getInstance()->myMoney($player) < $price){
			throw new BuyException("돈이 부족합니다. 구매에 필요한 금액 : " . EconomyAPI::getInstance()->getPlayerPreferredCurrency($player, false)->format($price));
		}
		EconomyAPI::getInstance()->reduceMoney($player, $price);
		$player->getInventory()->addItem($item);
	}

	public function sell(Player $player, int $count){
		if($count < 1){
			throw new SellException("갯수는 양수로 입력해주세요.");
		}
		$price = $this->sellPrice * $count;
		if($price < 0){
			throw new SellException("판매 불가능한 아이템입니다.");
		}
		$item = clone $this->item;
		$item->setCount($count);
		if(Util::itemHollCount($player, $item) < $count){
			throw new SellException("판매할 아이템이 부족합니다.");
		}
		$player->getInventory()->removeItem($item);
		EconomyAPI::getInstance()->addMoney($player, $price);
	}

	public function updateTile(ItemFrame $frame){
		$display = $frame->getFramedItem();
		if(!$display instanceof Item){// 아이템 액자의 아이템이 Item|null 반환이기 때문에 단지 안에 아이템이 없으면 통과
			$item = $this->getItem();
			$item->clearNamedTag();
			$text = "§f" . $this->getName() . "\n" . ($this->buyPrice < 0 ? "§c구매 불가" : "§b구매 : " . EconomyAPI::getInstance()->getDefaultCurrency()->format($this->buyPrice)) . "\n" . ($this->sellPrice < 0 ? "§c판매 불가" : "§b판매 : " . EconomyAPI::getInstance()->getDefaultCurrency()->format($this->sellPrice));
			$item->setCustomName($text);
			$frame->setFramedItem($item);
			$frame->getPos()->getWorld()->setBlock($frame->getPos(), $frame);
		}
	}

	public function jsonSerialize(){
		return [
			"id" => $this->id,
			"item" => [
				"id" => $this->item->getId(),
				"damage" => $this->item->getMeta(),
				"nbt_b64" => base64_encode((new LittleEndianNbtSerializer())->write(new TreeRoot($this->item->getNamedTag())))
			],
			"buyPrice" => $this->buyPrice,
			"sellPrice" => $this->sellPrice
		];
	}

	public static function jsonDeserialize(array $data){
		$market = (new \ReflectionClass(static::class))->newInstanceWithoutConstructor();
		if(!isset($data["id"]) or !isset($data["item"]["id"]) or !isset($data["buyPrice"]) or !isset($data["sellPrice"])){
			$values = [];
			foreach($data as $k => $v){
				$values[] = $k . ": " . $v;
			}
			throw new MarketDeserializeException("상점의 데이터가 일부 손실되었습니다. (" . implode(", ", $values) . ")");
		}
		$market->id = $data["id"];

		if(isset($data["item"]["nbt_b64"])){
			$nbt = (new LittleEndianNbtSerializer())->read(base64_decode($data["item"]["nbt_b64"]))->mustGetCompoundTag();
		}else{
			$nbt = null;
		}

		$market->item = ItemFactory::getInstance()->get((int) $data["item"]["id"], (int) $data["item"]["damage"] ?? 0, 1, $nbt);

		$market->buyPrice = $data["buyPrice"];
		$market->sellPrice = $data["sellPrice"];
		return $market;
	}
}
