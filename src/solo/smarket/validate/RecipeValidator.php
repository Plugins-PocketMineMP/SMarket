<?php

namespace solo\smarket\validate;

use pocketmine\crafting\FurnaceRecipe;
use pocketmine\item\ItemFactory;
use solo\smarket\SMarket;

class RecipeValidator implements Validator{

	protected $owner;

	public function __construct(SMarket $owner){
		$this->owner = $owner;
	}

	public function validate(){
		$itemList = new ItemList();
		foreach(array_values($this->owner->getMarketFactory()->getAllMarket()) as $market){
			if($market->getBuyPrice() < 0){
				continue;
			}
			$item = new Item($market->getItem(), $market->getBuyPrice());
			if(!$itemList->contains($item)){
				$itemList->add($item);
			}
		}
		foreach($this->owner->getServer()->getCraftingManager()->getShapedRecipes() as $recipes){
			foreach($recipes as $recipe){
				$ingredients = [];
				foreach($recipe->getIngredientMap() as $row){
					foreach($row as $realItem){
						$ingredients[] = $realItem;
					}
				}
				$ingredientList = new IngredientList();
				foreach($ingredients as $realItem){
					if($realItem->getId() === 0){// air
						continue;
					}
					$item = new Item($realItem);
					if(!$itemList->contains($item)){
						$itemList->add($item);
					}
					$ingredientList->addIngredient($itemList->getByItem($item->getItem()));
				}

				$results = $recipe->getResults();
				/** @var \pocketmine\item\Item $resultItem */
				$resultItem = array_pop($results);
				if($resultItem->getCount() > 1){
					$ingredientList->setDivider($resultItem->getCount());
				}
				$item = new Item($resultItem);
				if(!$itemList->contains($item)){
					$itemList->add($item);
				}
				$itemList->getByItem($item->getItem())->addIngredientList($ingredientList);
			}
		}
		foreach($this->owner->getServer()->getCraftingManager()->getShapelessRecipes() as $recipes){
			foreach($recipes as $recipe){
				$ingredients = [];
				foreach($recipe->getResults() as $result){
					$ingredients[] = $result;
				}
				$ingredientList = new IngredientList();
				foreach($ingredients as $realItem){
					if($realItem->getId() === 0){// air
						continue;
					}
					$item = new Item($realItem);
					if(!$itemList->contains($item)){
						$itemList->add($item);
					}
					$ingredientList->addIngredient($itemList->getByItem($item->getItem()));
				}

				$results = $recipe->getResults();
				/** @var \pocketmine\item\Item $resultItem */
				$resultItem = array_pop($results);
				if($resultItem->getCount() > 1){
					$ingredientList->setDivider($resultItem->getCount());
				}
				$item = new Item($resultItem);
				if(!$itemList->contains($item)){
					$itemList->add($item);
				}
				$itemList->getByItem($item->getItem())->addIngredientList($ingredientList);
			}
		}
		/**
		 * @param FurnaceRecipe[] $recipe
		 */
		foreach($this->owner->getServer()->getCraftingManager()->getFurnaceRecipeManager()->getAll() as $recipe){
			$ingredientList = new IngredientList();
			$item = new Item($recipe->getResult());
			if(!$itemList->contains($item)){
				$itemList->add($item);
			}
			$ingredientList->addIngredient($itemList->getByItem($recipe->getResult()));

			$resultItem = $recipe->getInput();
			if($resultItem->getCount() > 1){
				$ingredientList->setDivider($resultItem->getCount());
			}
			$item = new Item($resultItem);
			if(!$itemList->contains($item)){
				$itemList->add($item);
			}
			$itemList->getByItem($item->getItem())->addIngredientList($ingredientList);
		}
		$invalidMarketInfoList = [];
		foreach($itemList->getAll() as $item){
			$market = $this->owner->getMarketFactory()->getMarketByItem($item->getItem(), false);
			if($market === null){
				continue;
			}
			$sellPrice = $market->getSellPrice();

			/** @var IngredientList $ingredientList */
			foreach($item->getIngredietLists() as $ingredientList){
				$availableBuyPrice = $ingredientList->getPrice();
				if($availableBuyPrice >= 0 && $sellPrice >= 0 && $availableBuyPrice < $sellPrice){
					$howTo = [
						"조합재료 : " . $ingredientList->getName(),
						"조합결과 : " . $item->getName(false),
						"조합재료 구매가 : " . $availableBuyPrice,
						"조합결과 판매가 : " . $sellPrice
					];
					$invalidMarketInfoList[] = new InvalidMarketInfo("조합", $market, $availableBuyPrice, $howTo);
				}
			}
		}
		return $invalidMarketInfoList;
	}
}
