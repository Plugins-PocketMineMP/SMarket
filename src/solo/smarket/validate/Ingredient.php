<?php

namespace solo\smarket\validate;

use pocketmine\item\Item as RealItem;
use pocketmine\item\ItemFactory;
use solo\smarket\util\Util;

/**
 * 조합 Validation을 위한 클래스입니다.
 */
class Ingredient
{

	protected $item;
	protected $price;
	protected $checkMeta = true;

	public function __construct(RealItem $item, $price = -1)
	{
		if ($item->getMeta() == -1) {
			$this->checkMeta = false;
			$this->item = ItemFactory::getInstance()->get($item->getId(), 0, 1, $item->getNamedTag());
		} else {
			$this->item = ItemFactory::getInstance()->get($item->getId(), $item->getMeta(), 1, $item->getNamedTag());
		}
		$this->price = $price;
	}

	public function getName()
	{
		return Util::itemName($this->item);
	}

	public function getItem()
	{
		return $this->item;
	}

	// if damage is -1, do not check meta
	public function equals(Ingredient $ingredient)
	{
		return $ingredient->item->equals($this->item, $this->checkMeta, $this->checkMeta);
	}

	public function setPrice($price)
	{
		$this->price = ($price < 0) ? -1 : $price;
	}

	public function getPrice()
	{
		return $this->price;
	}
}
