<?php

/**
 * VFormImagesFix - PocketMine plugin.
 * Copyright (C) 2023 - 2025 VennDev
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

declare(strict_types=1);

namespace vennv\vformimagesfix;

use pocketmine\entity\Attribute;
use pocketmine\network\mcpe\NetworkSession;
use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\event\server\DataPacketSendEvent;
use pocketmine\network\mcpe\protocol\DataPacket;
use pocketmine\network\mcpe\protocol\ModalFormRequestPacket;
use pocketmine\network\mcpe\protocol\UpdateAttributesPacket;
use pocketmine\network\mcpe\protocol\types\entity\Attribute as NetworkAttribute;
use vennv\vapm\Async;
use vennv\vapm\Promise;
use vennv\vapm\VapmPMMP;
use Throwable;

final class Loader extends PluginBase implements Listener
{

    // How many packets to send per ModalFormRequestPacket.
    private const PACKETS_TO_SEND = 7;

    protected function onEnable(): void
    {
        VapmPMMP::init($this);
        $this->getServer()->getPluginManager()->registerEvents($this, $this);
    }

    /**
     * @throws Throwable
     */
    public function onDataPacketSend(DataPacketSendEvent $event): void
    {
        $packets = $event->getPackets();
        $targets = $event->getTargets();

        foreach ($packets as $packet) {
            foreach ($targets as $target) {
                if ($packet instanceof ModalFormRequestPacket) {

                    $player = $target->getPlayer();

                    if ($player !== null && $player->isOnline()) {

                        // Async Await to handle too many packets being sent at one time.
                        new Async(function() use ($player, $target) {
                            for ($i = 0; $i < self::PACKETS_TO_SEND; ++$i) {
                                Async::await(new Promise(function($resolve) use ($player, $target) {
                                    if ($target->isConnected()) {
                                        $attribute = $player->getAttributeMap()->get(Attribute::EXPERIENCE_LEVEL);

                                        $id = $attribute->getId();
                                        $minValue = $attribute->getMinValue();
                                        $maxValue = $attribute->getMaxValue();
                                        $value = $attribute->getValue();
                                        $defaultValue = $attribute->getDefaultValue();

                                        $networkAttribute = new NetworkAttribute($id, $minValue, $maxValue, $value, $defaultValue, []);

                                        $updateAttributePacket = UpdateAttributesPacket::create($player->getId(), [$networkAttribute], 0);

                                        $target->sendDataPacket($updateAttributePacket);
                                    }

                                    $resolve();
                                }));
                            }
                        });
                    }

                    break;
                }
            }
        }
    }

}