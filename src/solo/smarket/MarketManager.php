<?php

namespace solo\smarket;

use pocketmine\block\BlockLegacyIds;
use pocketmine\block\ItemFrame;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\server\DataPacketReceiveEvent;
use pocketmine\block\Block;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\ItemFrameDropItemPacket;
use pocketmine\network\mcpe\protocol\PlayerActionPacket;
use pocketmine\player\Player;
use pocketmine\world\Position;
use solo\smarket\util\MarketAlreadyExistsException;
use solo\smarket\util\Util;

class MarketManager implements Listener
{

	/** @var Market[] */
	private $markets = [];

	protected $selectedMarket = [];

	/** @var SMarket */
	private $owner;

	public function __construct(SMarket $owner)
	{
		$this->owner = $owner;

		$this->load();

		$this->owner->getServer()->getPluginManager()->registerEvents($this, $this->owner);
	}

	public function getMarket(Position $pos)
	{
		return $this->markets[Util::positionHash($pos)] ?? null;
	}

	public function setMarket(Position $pos, Market $market, bool $override = false)
	{
		$hash = Util::positionHash($pos);
		if (isset($this->markets[$hash]) && !$override) {
			throw new MarketAlreadyExistsException("같은 좌표에 마켓이 겹칩니다 : x=" . $pos->getX() . ", y=" . $pos->getY() . ", z=" . $pos->getZ() . ", level=" . $pos->getWorld()->getFolderName());
		}
		$this->markets[$hash] = $market;
	}

	public function removeMarket(Position $pos)
	{
		unset($this->markets[Util::positionHash($pos)]);
	}

	public function selectMarket(Player $player, Position $pos)
	{
		$this->selectedMarket[$player->getName()] = [
			"vector" => new Vector3($pos->x, $pos->y, $pos->z),
			"hash" => Util::positionHash($pos),
			"time" => time()
		];
	}

	public function getSelectedMarket(Player $player)
	{
		if (isset($this->selectedMarket[$player->getName()])) {
			$data = $this->selectedMarket[$player->getName()];
			if (time() - $data["time"] < 30 && $data["vector"]->distance($player->getPosition()) < 10) {
				return $this->markets[$data["hash"]] ?? null;
			}
			$this->removeSelectedMarket($player);
		}
		return null;
	}

	public function removeSelectedMarket(Player $player)
	{
		unset($this->selectedMarket[$player->getName()]);
	}

	public function generateMarketForm(Market $market)
	{
		return [
			"§f" . Util::itemName($market->getItem()),
			$market->getBuyPrice() < 0 ? "§c구매 불가" : "§b구매 : " . $market->getBuyPrice(),
			$market->getSellPrice() < 0 ? "§c판매 불가" : "§b판매 : " . $market->getSellPrice()
		];
	}

	public function generateMarketMessage(Player $player, Market $market)
	{
		$item = $market->getItem();

		$ret = [];
		if ($market->getBuyPrice() < 0 && $market->getSellPrice() < 0) {
			$ret = [
				"이 아이템은 구매 / 판매가 불가능합니다."
			];
		} else {
			$ret = [
				"§b" . $market->getName() . "§f 을(를) 구매 또는 판매하시겠습니까?§r§f",
				"보유한 금액 : §l§b" . $this->owner->getEconomyAPI()->getPlayerPreferredCurrency($player, false)->format($this->owner->getEconomyAPI()->myMoney($player)) . "§r§f, 보유한 아이템 수 : §l§b" . Util::itemHollCount($player, $item) . "개§r§f", ($market->getBuyPrice() < 0 ? "구매 불가" : "구매가 : §l§b" . $this->owner->getEconomyAPI()->getPlayerPreferredCurrency($player, false)->format($market->getBuyPrice())) . "§r§f  /  " . ($market->getSellPrice() < 0 ? "판매 불가" : "판매가 : §l§b" . $this->owner->getEconomyAPI()->getPlayerPreferredCurrency($player, false)->format($market->getSellPrice())) . "§r§f",
				"구매하려면 “§b/구매 <수량>§f”, 판매하려면 “§b/판매 <수량>§f”을 입력해주세요.§r§f"
			];
		}
		if ($player->hasPermission("smarket.command.sellprice")) {
			$ret[] = "§o§d * /판매가 <가격> 명령어로 해당 아이템의 판매가를 변경할 수 있습니다.";
		}
		if ($player->hasPermission("smarket.command.buyprice")) {
			$ret[] = "§o§d * /구매가 <가격> 명령어로 해당 아이템의 구매가를 변경할 수 있습니다.";
		}
		return $ret;
	}

	/**
	 * @priority HIGH
	 *
	 * @ignoreCancelled true
	 */
	public function handleDataPacketReceive(DataPacketReceiveEvent $event)
	{
		$packet = $event->getPacket();
		$player = $event->getOrigin()->getPlayer();
		if ($packet instanceof ItemFrameDropItemPacket) {
			$frame = $player->getWorld()->getBlock($pos = new Position($packet->x, $packet->y, $packet->z, $player->getWorld()));
			if ($frame instanceof ItemFrame) {
				$market = $this->getMarket($pos);
				if ($market !== null) {
					$event->setCancelled();
					if ($player->hasPermission("smarket.manage.remove")) {
						$this->removeMarket($pos);
						$frame->setFramedItem(null);
						$frame->getPos()->getWorld()->setBlock($frame->getPos(), $frame);
						$player->sendMessage(SMarket::$prefix . "상점을 삭제하였습니다.");
					} else {
						$player->sendPopup("§c상점을 부술 수 없습니다");
					}
				}
			}
		} elseif ($packet instanceof PlayerActionPacket) {
			//if ($packet->action === PlayerActionPacket::ACTION_INTERACT_BLOCK) {
			$v = new Position($packet->x, $packet->y, $packet->z, $player->getWorld());
			$block = $player->getWorld()->getBlock($v);
			if (($market = SMarket::getInstance()->getMarketManager()->getMarket($v)) instanceof Market && ($block instanceof ItemFrame)) {
				$event->setCancelled(true);
				if ($player->hasPermission("smarket.manage.remove")) {
					if ($packet->action === PlayerActionPacket::ACTION_START_BREAK) {
						$this->removeMarket($v);
						$block->setFramedItem(null);
						$block->getPos()->getWorld()->setBlock($block->getPos(), $block);
						$player->sendMessage(SMarket::$prefix . "상점을 삭제하였습니다.");
					} else {
						$market->updateTile($block);

						$player->sendMessage("§r§a============================§r");
						foreach ($this->generateMarketMessage($player, $market) as $message) {
							$player->sendMessage($message);
						}
						$player->sendMessage("§r§a============================§r");

						$this->selectMarket($player, $block->getPos());
					}
				} else {
					$market->updateTile($block);

					$player->sendMessage("§r§a============================§r");
					foreach ($this->generateMarketMessage($player, $market) as $message) {
						$player->sendMessage($message);
					}
					$player->sendMessage("§r§a============================§r");

					$this->selectMarket($player, $block->getPos());
				}
			}
			//}
		}
	}

	public function handlePlayerQuit(PlayerQuitEvent $event)
	{
		$this->removeSelectedMarket($event->getPlayer());
	}

	public function load()
	{
		$file = $this->owner->getDataFolder() . "installed_markets.json";
		if (file_exists($file)) {
			foreach (Util::jsonDecode(file_get_contents($file)) as $hash => $marketId) {
				$market = $this->owner->getMarketFactory()->getMarket($marketId);
				if ($market === null) {
					$this->owner->getServer()->getLogger()->critical("[SMarket] Does MarketFactory data loss? Market Id : " . $marketId);
					continue;
				}
				$this->markets[$hash] = $market;
			}
		}
	}

	public function save()
	{
		$data = [];
		foreach ($this->markets as $hash => $market) {
			$data[$hash] = $market->getId();
		}
		file_put_contents($this->owner->getDataFolder() . "installed_markets.json", json_encode($data));
	}
}
