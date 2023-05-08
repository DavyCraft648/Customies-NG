<?php
declare(strict_types=1);

namespace customiesdevs\customies\block;

use pocketmine\data\bedrock\block\BlockStateData;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\convert\BlockStateDictionary;
use pocketmine\network\mcpe\convert\BlockStateDictionaryEntry;
use pocketmine\network\mcpe\convert\BlockTranslator;
use pocketmine\network\mcpe\convert\TypeConverter;
use pocketmine\network\mcpe\protocol\ProtocolInfo;
use pocketmine\utils\SingletonTrait;
use pocketmine\utils\Utils;
use ReflectionProperty;
use RuntimeException;
use function array_keys;
use function array_map;
use function hash;
use function method_exists;
use function strcmp;
use function usort;

final class BlockPalette
{
    use SingletonTrait;

    /** @var BlockStateDictionaryEntry[][] */
    private array $states;
    /** @var BlockStateDictionaryEntry[] */
    private array $customStates = [];

    /** @var BlockStateDictionary[] */
    private array $dictionaries;
    /** @var ReflectionProperty[] */
    private array $bedrockKnownStates;
    /** @var ReflectionProperty[] */
    private array $lookupCache;

    public function __construct()
    {
        $protocolId = ProtocolInfo::CURRENT_PROTOCOL;
        $instance = TypeConverter::getInstance()->getBlockTranslator();
        if (isset($this->states[$protocolId])) {
            return;
        }
        $this->dictionaries[$protocolId] = $dictionary = $instance->getBlockStateDictionary();
        $this->states[$protocolId] = $dictionary->getStates();
        $this->bedrockKnownStates[$protocolId] = $bedrockKnownStates = new ReflectionProperty($dictionary, "states");
        $bedrockKnownStates->setAccessible(true);
        $this->lookupCache[$protocolId] = $lookupCache = new ReflectionProperty($dictionary, "stateDataToStateIdLookupCache");
        $lookupCache->setAccessible(true);
    }

    /**
     * @return BlockStateDictionaryEntry[]
     */
    public function getStates(int $mappingProtocol): array
    {
        return $this->states[$mappingProtocol];
    }

    /**
     * @return BlockStateDictionaryEntry[]
     */
    public function getCustomStates(): array
    {
        return $this->customStates;
    }

    /**
     * Inserts the provided state in to the correct position of the palette.
     */
    public function insertState(CompoundTag $state, int $meta = 0): void
    {
        if ($state->getString("name") === "") {
            throw new RuntimeException("Block state must contain a StringTag called 'name'");
        }
        if ($state->getCompoundTag("states") === null) {
            throw new RuntimeException("Block state must contain a CompoundTag called 'states'");
        }
        $stateData = BlockStateData::fromNbt($state);
        $this->sortWith($entry = new BlockStateDictionaryEntry($stateData->getName(), $stateData->getStates(), $meta));
        $this->customStates[] = $entry;
    }

    /**
     * Sorts the palette's block states in the correct order, also adding the provided state to the array.
     */
    private function sortWith(BlockStateDictionaryEntry $newState): void
    {
        foreach ($this->states as $protocol => $protocolStates) {
            // To sort the block palette we first have to split the palette up in to groups of states. We only want to sort
            // using the name of the block, and keeping the order of the existing states.
            $states = [];
            foreach ($protocolStates as $state) {
                $states[$state->getStateName()][] = $state;
            }
            // Append the new state we are sorting with at the end to preserve existing order.
            $states[$newState->getStateName()][] = $newState;

            $names = array_keys($states);
            // As of 1.18.30, blocks are sorted using a fnv164 hash of their names.
            usort($names, static fn(string $a, string $b) => strcmp(hash("fnv164", $a), hash("fnv164", $b)));
            $sortedStates = [];
            foreach ($names as $name) {
                // With the sorted list of names, we can now go back and add all the states for each block in the correct order.
                foreach ($states[$name] as $state) {
                    $sortedStates[] = $state;
                }
            }
            $this->states[$protocol] = $sortedStates;
            $this->bedrockKnownStates[$protocol]->setValue($this->dictionaries[$protocol], $sortedStates);
            $stateDataToStateIdLookupCache = [];
            $table = [];
            foreach (array_map(fn(BlockStateDictionaryEntry $entry) => $entry->generateStateData(), $this->states[$protocol]) as $stateId => $stateData) {
                foreach ($stateData->getStates() as $stateNbt) {
                    $table[$stateData->getName()][$stateNbt->getValue()] = $stateId;
                }
            }
            foreach (Utils::stringifyKeys($table) as $name => $stateIds) {
                if (count($stateIds) === 1) {
                    $stateDataToStateIdLookupCache[$name] = $stateIds[array_key_first($stateIds)];
                } else {
                    $stateDataToStateIdLookupCache[$name] = $stateIds;
                }
            }
            $this->lookupCache[$protocol]->setValue($this->dictionaries[$protocol], $stateDataToStateIdLookupCache);
        }
    }
}
