<?php

namespace solo\smarket\process;

use pocketmine\block\BlockLegacyIds;
use pocketmine\block\ItemFrame;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\item\ItemIds;
use pocketmine\player\Player;
use solo\smarket\SMarket;
use solo\smarket\Process;
use solo\smarket\util\MarketAlreadyExistsException;

class MarketCreateProcess extends Process
{

	public function __construct(Player $player)
	{
		parent::__construct($player);

		$this->player->sendMessage(SMarket::$prefix . "표지판이나 아이템 액자를 터치하면 상점이 생성됩니다.");
	}

	public function getName()
	{
		return "상점생성";
	}

	public function handlePlayerInteract(PlayerInteractEvent $event)
	{
		if ($event->getAction() === PlayerInteractEvent::RIGHT_CLICK_BLOCK) {
			$block = $event->getBlock();
			if (
				$block->getId() == BlockLegacyIds::WALL_SIGN
				|| $block->getId() == BlockLegacyIds::SIGN_POST
				|| $block->getId() == BlockLegacyIds::ITEM_FRAME_BLOCK
			) {
				/*
				$tile = $block->getPos()->getWorld()->($block);
				if ($tile instanceof Sign || $tile instanceof ItemFrame) {
					$item = $event->getPlayer()->getInventory()->getItemInHand();
					if ($item->getId() === Item::AIR) {
						return;
					}
					$event->setCancelled();
					$market = SMarket::getInstance()->getMarketFactory()->getMarketByItem($item);

					$marketManager = SMarket::getInstance()->getMarketManager();
					try {
						$marketManager->setMarket($block, $market);
					} catch (MarketAlreadyExistsException $e) {
						$this->player->sendMessage(SMarket::$prefix . $e->getMessage());
						return;
					}
					$market->updateTile($tile);
					$this->player->sendMessage(SMarket::$prefix . "상점을 생성하였습니다.");
				}
				*/
				if ($block instanceof ItemFrame) {
					$item = $event->getPlayer()->getInventory()->getItemInHand();
					if ($item->getId() === ItemIds::AIR) {
						return;
					}
					$event->setCancelled();
					$market = SMarket::getInstance()->getMarketFactory()->getMarketByItem($item);

					$marketManager = SMarket::getInstance()->getMarketManager();
					try {
						$marketManager->setMarket($block->getPos(), $market);
					} catch (MarketAlreadyExistsException $e) {
						$this->player->sendMessage(SMarket::$prefix . $e->getMessage());
						return;
					}
					$market->updateTile($block);
					$this->player->sendMessage(SMarket::$prefix . "상점을 생성하였습니다.");
				}
			}
		}
	}
}
